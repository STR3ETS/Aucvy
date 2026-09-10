<?php

declare(strict_types=1);

namespace App\Auctions\Support;

use App\Enums\BidStatus;
use App\Enums\BidType;
use App\Models\Auction;
use App\Models\AutoBid;
use App\Models\Bid;

/**
 * Bepaalt in één berekening wie leidt en tegen welke prijs.
 *
 * Niet stap voor stap doorrekenen: twee automatische maxima die elkaar om
 * beurten aftroeven worden naïef een oneindige lus, of ze eindigen bij de
 * verkeerde winnaar (hfst. 6).
 *
 * ---------------------------------------------------------------------------
 * Handmatig bod versus maximum
 * ---------------------------------------------------------------------------
 *
 * Het platform heeft twee losse acties, en ze betekenen niet hetzelfde:
 *
 *   "Bied € 13.100"              een hard bod. Win je, dan is de prijs € 13.100.
 *   "Automatisch meestappen tot" een plafond. Win je, dan betaal je alleen wat
 *                                nodig was om de nummer twee net voor te zijn.
 *
 * Dat verschil is precies waarom er twee endpoints zijn (hfst. 7) en waarom de
 * kavelpagina twee bedieningen toont. Eén van beide betekenissen kiezen zou de
 * andere knop een leugen maken.
 */
final class ProxyResolver
{
    /**
     * @param  int  $challengerAmountCents  het bod, of het maximum
     * @param  bool  $amountIsMaximum  true bij automatisch bieden
     */
    public function resolve(
        Auction $auction,
        int $challengerId,
        int $challengerAmountCents,
        bool $amountIsMaximum,
    ): ProxyOutcome {
        $increments = IncrementTable::forAuction($auction);
        $challengerType = $amountIsMaximum ? BidType::Proxy : BidType::Manual;

        $opponent = $this->strongestOpponent($auction, $challengerId);

        // Niemand om tegen te bieden: de uitdager leidt tegen de laagste prijs
        // die is toegestaan. Bij een hard bod is dat het geboden bedrag zelf.
        if ($opponent === null) {
            $price = $amountIsMaximum
                ? min($challengerAmountCents, $this->openingPrice($auction, $increments))
                : $challengerAmountCents;

            return new ProxyOutcome(
                bids: [new ResolvedBid($challengerId, $price, $challengerType, BidStatus::Winning)],
                leaderId: $challengerId,
                priceCents: $price,
                challengerId: $challengerId,
            );
        }

        // De uitdager wint alleen met een strikt hoger bedrag. Bij gelijkspel
        // wint de vroegste inzending, en die is per definitie de ander -
        // vandaar dat placed_at en sequence ertoe doen (hfst. 6).
        if ($challengerAmountCents > $opponent->amountCents) {
            $price = $amountIsMaximum
                ? min(
                    $challengerAmountCents,
                    $opponent->amountCents + $increments->stepFor($opponent->amountCents),
                )
                : $challengerAmountCents;

            return new ProxyOutcome(
                bids: [new ResolvedBid($challengerId, $price, $challengerType, BidStatus::Winning)],
                leaderId: $challengerId,
                priceCents: $price,
                challengerId: $challengerId,
            );
        }

        /*
         | De uitdager verliest. De tegenstander stapt automatisch mee tot net
         | boven het bod van de uitdager - niet tot zijn eigen maximum, want dat
         | is het hele punt van een maximum.
         |
         | Twee rijen, in deze volgorde: eerst het bod dat gedaan werd, dan het
         | automatische antwoord. Zo leest de biedhistorie als wat er gebeurde.
         */
        $price = min(
            $opponent->amountCents,
            $challengerAmountCents + $increments->stepFor($challengerAmountCents),
        );

        return new ProxyOutcome(
            bids: [
                new ResolvedBid($challengerId, $challengerAmountCents, $challengerType, BidStatus::Outbid),
                new ResolvedBid($opponent->userId, $price, BidType::Proxy, BidStatus::Winning),
            ],
            leaderId: $opponent->userId,
            priceCents: $price,
            challengerId: $challengerId,
        );
    }

    /**
     * De sterkste tegenstander: het hoogste actieve maximum van een ander, of
     * de huidige hoogste bieder als die geen maximum heeft ingesteld.
     *
     * Bij gelijke bedragen wint de oudste toezegging.
     */
    private function strongestOpponent(Auction $auction, int $challengerId): ?Commitment
    {
        $candidates = [];

        $topAutoBid = AutoBid::query()
            ->where('auction_id', $auction->id)
            ->where('user_id', '!=', $challengerId)
            ->where('active', true)
            ->orderByDesc('max_amount_cents')
            // Gelijkspel: wie het eerst instelde.
            ->orderBy('id')
            ->first();

        if ($topAutoBid !== null) {
            $candidates[] = new Commitment(
                userId: $topAutoBid->user_id,
                amountCents: $topAutoBid->max_amount_cents,
                placedOrder: $topAutoBid->id,
            );
        }

        /*
         | De staande hoogste bieder telt óók mee, ook zonder maximum. Anders
         | zou een nieuw maximum van € 20.000 tegen een staand bod van € 12.850
         | de prijs terugzetten naar de startprijs.
         */
        $standing = Bid::query()
            ->where('auction_id', $auction->id)
            ->where('status', BidStatus::Winning)
            ->where('user_id', '!=', $challengerId)
            ->orderByDesc('sequence')
            ->first();

        if ($standing !== null) {
            $candidates[] = new Commitment(
                userId: $standing->user_id,
                amountCents: $standing->amount_cents,
                placedOrder: $standing->sequence,
            );
        }

        if ($candidates === []) {
            return null;
        }

        usort(
            $candidates,
            fn (Commitment $a, Commitment $b): int => $b->amountCents <=> $a->amountCents
                ?: $a->placedOrder <=> $b->placedOrder,
        );

        return $candidates[0];
    }

    /**
     * De prijs waarop een eerste deelnemer binnenkomt: de startprijs, of één
     * stap boven de stand als er al geboden is.
     */
    private function openingPrice(Auction $auction, IncrementTable $increments): int
    {
        return $increments->minimumBid(
            currentPriceCents: $auction->current_price_cents,
            hasBids: $auction->bid_count > 0,
            startPriceCents: $auction->start_price_cents,
        );
    }
}
