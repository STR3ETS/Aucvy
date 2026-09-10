<?php

declare(strict_types=1);

namespace App\Auctions\Support;

use App\Auctions\Events\AuctionExtended;
use App\Auctions\Events\BidPlaced;
use App\Auctions\Events\BuyNowDisabled;
use App\Auctions\Events\Outbid;
use App\Enums\AuctionEventType;
use App\Enums\BidStatus;
use App\Enums\BidType;
use App\Models\Auction;
use App\Models\AuctionEvent;
use App\Models\Bid;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Het schrijfpad van een biedronde.
 *
 * Gedeeld door PlaceBid en PlaceAutoBid, zodat er één plek is waar de stand,
 * de tellers, de sequence en de events bijgewerkt worden. Twee kopieën van dit
 * pad zouden vroeg of laat uit elkaar lopen, en dan klopt de eventstroom van
 * de ene route wel en de andere niet.
 *
 * Alles hierin draait binnen de rij-lock die de aanroepende Action vasthoudt.
 */
final class BidWriter
{
    /**
     * Schrijft de uitkomst weg. Roep dit uitsluitend aan binnen de transactie
     * die de veiling gelockt heeft.
     */
    public function commit(
        Auction $auction,
        ProxyOutcome $outcome,
        string $idempotencyKey,
        ?string $ip = null,
        ?string $userAgent = null,
    ): WriteResult {
        // Vóór het schrijven, zodat de nieuwe eindtijd in dezelfde ronde zit
        // als het bod dat hem veroorzaakte.
        $previousEndsAt = $this->extendIfClosing($auction);

        $outbidUserIds = $this->demotePreviousLeaders($auction, $outcome);

        $bids = $this->writeBids($auction, $outcome, $idempotencyKey, $ip, $userAgent);

        if ($previousEndsAt !== null) {
            $this->writeEvent($auction, AuctionEventType::Extended, [
                'previous_ends_at' => $previousEndsAt->toIso8601String(),
                'ends_at' => $auction->ends_at->toIso8601String(),
                'extension_count' => $auction->extension_count,
            ]);
        }

        $buyNowDisabled = $this->updateAuctionState($auction, $outcome);

        return new WriteResult($bids, $previousEndsAt, $outbidUserIds, $buyNowDisabled);
    }

    /**
     * Een directe verkoop: afmijnen of koop-direct.
     *
     * Anders dan een gewoon bod is er hier niets op te lossen - er is geen
     * tegenpartij en geen staffel. Eén winnende rij, de stand erop, en daarna
     * sluit de aanroepende Action de veiling meteen.
     *
     * Roep dit uitsluitend aan binnen de transactie die de veiling gelockt
     * heeft.
     */
    public function commitImmediateSale(
        Auction $auction,
        User $user,
        int $priceCents,
        BidType $type,
        string $idempotencyKey,
        ?string $ip = null,
        ?string $userAgent = null,
    ): Bid {
        $sequence = ++$auction->last_sequence;

        $bid = Bid::create([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'amount_cents' => $priceCents,
            'type' => $type,
            'status' => BidStatus::Winning,
            'sequence' => $sequence,
            'idempotency_key' => $idempotencyKey,
            'placed_at' => now(),
            'ip' => $ip,
            'user_agent' => $userAgent,
        ]);

        // Een eventueel staand bod uit de veilingfase verliest hier zijn plek.
        Bid::query()
            ->where('auction_id', $auction->id)
            ->whereKeyNot($bid->id)
            ->where('status', BidStatus::Winning)
            ->update(['status' => BidStatus::Outbid]);

        $this->writeEvent($auction, AuctionEventType::BidPlaced, [
            'bid_id' => $bid->id,
            'amount_cents' => $bid->amount_cents,
            'type' => $bid->type->value,
            'is_automatic' => false,
            'status' => $bid->status->value,
            // Via de relatie en niet via $user: de aanroeper kan een model
            // meegeven dat niet alle kolommen bij zich draagt.
            'bidder_label' => $bid->user->publicName(),
        ], $sequence);

        $auction->current_price_cents = $priceCents;
        $auction->bid_count = Bid::where('auction_id', $auction->id)->count();
        $auction->bidder_count = Bid::where('auction_id', $auction->id)->distinct()->count('user_id');
        $auction->save();

        return $bid;
    }

    /**
     * Wat er de deur uit gaat zodra de transactie echt gecommit is.
     *
     * Uitzenden binnen de transactie stuurt een stand rond die nog kan
     * terugrollen - en dan biedt iemand op informatie die nooit heeft
     * bestaan (hfst. 6).
     */
    public function announce(Auction $auction, ProxyOutcome $outcome, WriteResult $result): void
    {
        $auction->refresh();

        BidPlaced::dispatch($auction, $this->currentWinningBid($auction));

        if ($result->wasExtended()) {
            AuctionExtended::dispatch($auction, $result->previousEndsAt, $auction->extension_count);
        }

        if ($result->buyNowDisabled) {
            BuyNowDisabled::dispatch(
                $auction,
                $auction->buy_now_price_cents,
                $outcome->priceCents,
            );
        }

        foreach (User::whereKey($result->outbidUserIds)->get() as $outbidUser) {
            Outbid::dispatch(
                $auction,
                $outbidUser,
                $outcome->priceCents,
                $outcome->wasCounteredAutomatically(),
            );
        }
    }

    /**
     * Verlengt de veiling als er binnen het slotvenster geboden wordt.
     *
     * Onbeperkt herhaalbaar: sluiten gebeurt pas als het echt stil is. De
     * teller loopt zichtbaar op, want "2x verlengd door biedingen in de
     * slotfase" is een verkoopargument (hfst. 13.3).
     *
     * @return CarbonImmutable|null de vorige eindtijd, of null als er niet verlengd is
     */
    private function extendIfClosing(Auction $auction): ?CarbonImmutable
    {
        $secondsLeft = $auction->ends_at->getTimestamp() - now()->getTimestamp();

        if ($secondsLeft > $auction->anti_snipe_window_s) {
            return null;
        }

        $previous = $auction->ends_at;

        $auction->ends_at = $auction->ends_at->addSeconds($auction->anti_snipe_extend_s);
        $auction->extension_count++;

        return $previous;
    }

    /**
     * Zet iedereen die de leiding kwijtraakt op `outbid`.
     *
     * @return array<int, int> de gebruikers die een bericht moeten krijgen
     */
    private function demotePreviousLeaders(Auction $auction, ProxyOutcome $outcome): array
    {
        $previousLeaderIds = Bid::query()
            ->where('auction_id', $auction->id)
            ->where('status', BidStatus::Winning)
            ->pluck('user_id')
            ->unique()
            ->all();

        Bid::query()
            ->where('auction_id', $auction->id)
            ->where('status', BidStatus::Winning)
            ->update(['status' => BidStatus::Outbid]);

        // Wie na deze ronde nog steeds leidt, is niet overboden.
        return array_values(array_diff($previousLeaderIds, [$outcome->leaderId]));
    }

    /**
     * @return array<int, Bid>
     */
    private function writeBids(
        Auction $auction,
        ProxyOutcome $outcome,
        string $idempotencyKey,
        ?string $ip,
        ?string $userAgent,
    ): array {
        $written = [];

        foreach ($outcome->bids as $index => $resolved) {
            $sequence = ++$auction->last_sequence;

            $bid = Bid::create([
                'auction_id' => $auction->id,
                'user_id' => $resolved->userId,
                'amount_cents' => $resolved->amountCents,
                'type' => $resolved->type,
                'status' => $resolved->status,
                'sequence' => $sequence,
                /*
                 | Alleen het bod van de uitdager draagt de sleutel die de
                 | client meestuurde. Een automatisch tegenbod krijgt een eigen
                 | afgeleide sleutel: anders zou de unieke index afgaan op een
                 | rij die de client nooit heeft aangevraagd.
                 */
                'idempotency_key' => $index === 0
                    ? $idempotencyKey
                    : $idempotencyKey.':proxy:'.$sequence,
                'placed_at' => now(),
                // Herkomst leggen we alleen vast bij een menselijke handeling.
                'ip' => $index === 0 ? $ip : null,
                'user_agent' => $index === 0 ? $userAgent : null,
            ]);

            $this->writeEvent($auction, AuctionEventType::BidPlaced, [
                'bid_id' => $bid->id,
                'amount_cents' => $bid->amount_cents,
                'type' => $bid->type->value,
                'is_automatic' => $bid->type->isAutomatic(),
                'status' => $bid->status->value,
                'bidder_label' => $bid->user->publicName(),
            ], $sequence);

            $written[] = $bid;
        }

        return $written;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeEvent(
        Auction $auction,
        AuctionEventType $type,
        array $payload,
        ?int $sequence = null,
    ): void {
        AuctionEvent::create([
            'auction_id' => $auction->id,
            'sequence' => $sequence ?? ++$auction->last_sequence,
            'type' => $type,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    /**
     * @return bool of koop-direct in deze ronde is vervallen
     */
    private function updateAuctionState(Auction $auction, ProxyOutcome $outcome): bool
    {
        $auction->current_price_cents = $outcome->priceCents;

        // Tellers in dezelfde transactie als het bod: anders kost elke
        // lijstweergave een aggregatie (hfst. 5).
        $auction->bid_count = Bid::where('auction_id', $auction->id)->count();
        $auction->bidder_count = Bid::where('auction_id', $auction->id)
            ->distinct()
            ->count('user_id');

        // Niet de reserveprijs zelf is publiek, wel of hij gehaald is.
        if ($auction->reserve_price_cents !== null) {
            $auction->reserve_met = $outcome->priceCents >= $auction->reserve_price_cents;
        }

        $buyNowDisabled = $this->disableBuyNowIfOvertaken($auction, $outcome->priceCents);

        $auction->save();

        return $buyNowDisabled;
    }

    /**
     * Koop-direct vervalt zodra er tot aan of boven de vaste prijs geboden is.
     *
     * Dit is een transitie en geen berekening: er komt een eigen event, zodat
     * elke open client de knop ziet verdwijnen mét uitleg. Zou je het afleiden
     * bij het klikken, dan krijgt de koper een fout op het moment dat hij
     * dacht te kopen (hfst. 6).
     */
    private function disableBuyNowIfOvertaken(Auction $auction, int $priceCents): bool
    {
        if (! $auction->buy_now_active || $auction->buy_now_price_cents === null) {
            return false;
        }

        if ($priceCents < $auction->buy_now_price_cents) {
            return false;
        }

        $auction->buy_now_active = false;

        $this->writeEvent($auction, AuctionEventType::BuyNowDisabled, [
            'buy_now_price_cents' => $auction->buy_now_price_cents,
            'current_price_cents' => $priceCents,
            'reason' => 'outbid',
        ]);

        return true;
    }

    private function currentWinningBid(Auction $auction): Bid
    {
        return Bid::query()
            ->with('user')
            ->where('auction_id', $auction->id)
            ->where('status', BidStatus::Winning)
            ->orderByDesc('sequence')
            ->sole();
    }
}
