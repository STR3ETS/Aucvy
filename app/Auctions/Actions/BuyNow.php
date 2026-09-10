<?php

declare(strict_types=1);

namespace App\Auctions\Actions;

use App\Auctions\Exceptions\BidRejected;
use App\Auctions\Exceptions\BidRejectionReason;
use App\Auctions\Support\BidGuards;
use App\Auctions\Support\BidWriter;
use App\Enums\BidType;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\Money\OrderCosts;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Koop-direct tegen de vaste prijs.
 *
 * Twee stappen, en dat is geen omslachtigheid maar een eis uit de mobiele
 * buildlijst: geen schuifknop, maar een knop plus een expliciete bevestiging.
 * Per ongeluk € 13.750 uitgeven mag niet één tik kosten (hfst. 6).
 *
 *   preview()  toont de volledige kostenopbouw, wijzigt niets
 *   handle()   koopt daadwerkelijk
 */
final class BuyNow
{
    public function __construct(
        private readonly BidGuards $guards,
        private readonly BidWriter $writer,
        private readonly CloseAuction $closeAuction,
    ) {}

    /**
     * Wat de koper te zien krijgt vóór hij bevestigt.
     *
     * Uit dezelfde Money-klasse als de order die er straks uit rolt - anders
     * kan het bedrag op het bevestigingsscherm afwijken van de factuur.
     */
    public function preview(Auction $auction, int $transportCents = 0): OrderCosts
    {
        $auction->loadMissing('lot');

        $this->guardAvailable($auction);

        return Money::forOrder($auction->lot, $auction->buy_now_price_cents, $transportCents);
    }

    public function handle(
        Auction $auction,
        User $user,
        string $idempotencyKey,
        ?string $ip = null,
        ?string $userAgent = null,
    ): Order {
        // Een retry na een timeout mag niet nóg een keer afrekenen. Expliciet
        // opvragen in plaats van via de relatie: lazy loading staat uit.
        if ($existing = Bid::firstWhere('idempotency_key', $idempotencyKey)) {
            return Order::where('auction_id', $existing->auction_id)->sole();
        }

        return DB::transaction(function () use ($auction, $user, $idempotencyKey, $ip, $userAgent): Order {
            $locked = Auction::query()
                ->whereKey($auction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->setRelation('lot', $locked->lot()->sole());

            $this->guards->ensureOpen($locked);
            $this->guards->ensureNotSeller($locked, $user);
            $this->guards->ensureVerified($user);
            $this->guardAvailable($locked);

            $price = $locked->buy_now_price_cents;

            $this->guards->ensureDepositCovers($locked, $user, $price);

            $this->writer->commitImmediateSale(
                auction: $locked,
                user: $user,
                priceCents: $price,
                type: BidType::BuyNow,
                idempotencyKey: $idempotencyKey,
                ip: $ip,
                userAgent: $userAgent,
            );

            $locked->buy_now_active = false;
            $locked->save();

            $order = $this->closeAuction->handle($locked);

            return $order ?? throw new LogicException(
                "Koop-direct op veiling {$locked->id} leverde geen order op."
            );
        }, attempts: 3);
    }

    /**
     * Koop-direct kan vervallen zijn doordat er inmiddels boven de vaste prijs
     * geboden is. De knop hoort dan al verdwenen te zijn op het scherm; komt
     * het verzoek er tóch, dan krijgt de koper uitleg in plaats van een fout.
     */
    private function guardAvailable(Auction $auction): void
    {
        if (! $auction->buy_now_active || $auction->buy_now_price_cents === null) {
            throw BidRejected::because(BidRejectionReason::BuyNowUnavailable);
        }
    }
}
