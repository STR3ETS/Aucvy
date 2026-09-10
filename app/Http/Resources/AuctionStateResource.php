<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Auctions\Support\BidGuards;
use App\Auctions\Support\DutchPriceCalculator;
use App\Auctions\Support\IncrementTable;
use App\Auctions\Support\ViewerPresence;
use App\Enums\BidStatus;
use App\Enums\SaleType;
use App\Models\Auction;
use App\Models\AutoBid;
use App\Models\Bid;
use App\Models\Deposit;
use App\Models\User;
use App\Support\Money\Money;

/**
 * De volledige veilingstand als JSON.
 *
 * Eén vorm voor twee doelen: de eerste render van de kavelpagina krijgt hem
 * als prop mee, en `/state` levert precies hetzelfde. Zo kan de pagina meteen
 * kloppen zonder websocket - belangrijk voor SEO en voor de eerste seconde op
 * mobiel (hfst. 13.3) - en is er geen tweede plek waar de vorm kan afwijken.
 *
 * ---------------------------------------------------------------------------
 * Payload-hygiëne
 * ---------------------------------------------------------------------------
 *
 * Wat hier níét in staat: volledige namen, e-mailadressen, de reserveprijs, en
 * het maximumbod van iemand anders. Alleen de kijker zelf ziet zijn eigen
 * maximum terug. Een maximumbod dat lekt, is een gebruiker die geld verliest
 * (hfst. 7 en 15).
 */
final class AuctionStateResource
{
    public function __construct(
        private readonly Auction $auction,
        private readonly ?User $viewer = null,
    ) {}

    public static function make(Auction $auction, ?User $viewer = null): self
    {
        return new self($auction, $viewer);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $auction = $this->auction->loadMissing('lot');
        $saleType = $auction->lot->sale_type;

        return [
            'auction_id' => $auction->id,
            'lot_public_id' => $auction->lot->public_id,

            /*
             | Waar de client staat in de gebeurtenissenstroom. Komt er een
             | event met een hoger nummer binnen dan verwacht, dan haalt hij
             | /events?after= op; lukt dat niet, dan blokkeert het biedpaneel
             | tot de stand ververst is (hfst. 11).
             */
            'sequence' => $auction->last_sequence,
            'server_time' => now()->toIso8601String(),

            'sale_type' => $saleType->value,
            'status' => $this->status(),

            'price_cents' => $this->currentPrice(),
            'price_formatted' => Money::format($this->currentPrice()),

            'bid_count' => $auction->bid_count,
            'bidder_count' => $auction->bidder_count,

            // Alleen een aantal, nooit wie (hfst. 7).
            'watching_now' => app(ViewerPresence::class)->count($auction->id),

            'ends_at' => $auction->ends_at->toIso8601String(),
            'original_ends_at' => $auction->original_ends_at->toIso8601String(),
            'extension_count' => $auction->extension_count,
            'anti_snipe_window_seconds' => $auction->anti_snipe_window_s,

            // Niet de reserveprijs zelf is publiek, wel of hij gehaald is.
            'has_reserve' => $auction->reserve_price_cents !== null,
            'reserve_met' => $auction->reserve_met,

            'minimum_bid_cents' => $this->minimumBid($saleType),
            'quick_bid_cents' => $this->quickBids($saleType),
            'buy_now' => $this->buyNow(),
            'dutch' => $this->dutch($saleType),

            'history' => $this->history(),
            'viewer' => $this->viewer(),
        ];
    }

    private function status(): string
    {
        if ($this->auction->isClosed()) {
            return 'closed';
        }

        return $this->auction->isInAntiSnipeWindow() ? 'closing' : 'live';
    }

    /**
     * Bij een afslag is de prijs een functie van de tijd; de opgeslagen kolom
     * loopt daar bewust achter (hfst. 6).
     */
    private function currentPrice(): int
    {
        if ($this->auction->lot->sale_type === SaleType::Dutch && ! $this->auction->isClosed()) {
            return app(DutchPriceCalculator::class)->priceAt($this->auction);
        }

        return $this->auction->current_price_cents;
    }

    private function minimumBid(SaleType $saleType): ?int
    {
        if ($saleType !== SaleType::Auction || $this->auction->isClosed()) {
            return null;
        }

        return app(BidGuards::class)->minimumBid($this->auction);
    }

    /**
     * Drie snelkeuzes voor het biedformulier: het minimum en de twee volgende
     * staffelstappen. Serverzijdig berekend, zodat een tredegrens in de
     * staffel (€ 500, € 5.000) nooit client-side benaderd hoeft te worden.
     *
     * @return array<int, int>|null
     */
    private function quickBids(SaleType $saleType): ?array
    {
        $minimum = $this->minimumBid($saleType);

        if ($minimum === null) {
            return null;
        }

        $table = IncrementTable::forAuction($this->auction);
        $second = $minimum + $table->stepFor($minimum);
        $third = $second + $table->stepFor($second);

        return [$minimum, $second, $third];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buyNow(): ?array
    {
        if ($this->auction->buy_now_price_cents === null) {
            return null;
        }

        return [
            'active' => $this->auction->buy_now_active && ! $this->auction->isClosed(),
            'price_cents' => $this->auction->buy_now_price_cents,
            'price_formatted' => Money::format($this->auction->buy_now_price_cents),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dutch(SaleType $saleType): ?array
    {
        if ($saleType !== SaleType::Dutch) {
            return null;
        }

        // Alles wat de client nodig heeft om zélf af te tellen, met dezelfde
        // formule als de server.
        return app(DutchPriceCalculator::class)->stateFor($this->auction);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function history(): array
    {
        return Bid::query()
            ->with('user')
            ->where('auction_id', $this->auction->id)
            ->where('status', '!=', BidStatus::Rejected)
            ->orderByDesc('sequence')
            ->limit(25)
            ->get()
            ->map(fn (Bid $bid): array => $bid->toPublicPayload())
            ->all();
    }

    /**
     * Wat alleen de kijker zelf aangaat.
     *
     * Zijn eigen maximum staat hier wél in - dat is zijn eigen informatie.
     * Voor iedereen anders bestaat het niet.
     *
     * @return array<string, mixed>|null
     */
    private function viewer(): ?array
    {
        if ($this->viewer === null) {
            return null;
        }

        $ownBids = Bid::query()
            ->where('auction_id', $this->auction->id)
            ->where('user_id', $this->viewer->id);

        $isLeading = (clone $ownBids)->where('status', BidStatus::Winning)->exists();
        $hasBid = $ownBids->exists();

        $autoBid = AutoBid::query()
            ->where('auction_id', $this->auction->id)
            ->where('user_id', $this->viewer->id)
            ->where('active', true)
            ->first();

        return [
            'state' => match (true) {
                $this->auction->isClosed() && $isLeading => 'won',
                $this->auction->isClosed() && $hasBid => 'lost',
                $isLeading => 'highest',
                $hasBid => 'outbid',
                default => 'idle',
            },
            'can_bid' => $this->viewer->kyc_status->allowsBidding(),
            'is_seller' => $this->auction->lot->seller_id === $this->viewer->id,
            'max_amount_cents' => $autoBid?->max_amount_cents,

            /*
             | De borg, vooraf op de pagina en niet pas bij het klikken
             | (hfst. 9): boven de drempel is een reservering nodig, en de
             | kijker ziet meteen of hij die al heeft staan.
             */
            'deposit' => [
                'threshold_cents' => (int) config('aucvy.deposit.threshold_cents'),
                'covered' => Deposit::query()
                    ->where('auction_id', $this->auction->id)
                    ->where('user_id', $this->viewer->id)
                    ->covering()
                    ->exists(),
            ],
        ];
    }
}
