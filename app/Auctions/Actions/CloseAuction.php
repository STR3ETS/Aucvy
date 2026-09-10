<?php

declare(strict_types=1);

namespace App\Auctions\Actions;

use App\Auctions\Events\AuctionClosed;
use App\Enums\AuctionEventType;
use App\Enums\BidStatus;
use App\Enums\DepositStatus;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Models\Auction;
use App\Models\AuctionEvent;
use App\Models\Bid;
use App\Models\Deposit;
use App\Models\Order;
use App\Support\Money\Money;
use Illuminate\Support\Facades\DB;

/**
 * Sluit een veiling af en legt de uitkomst vast.
 *
 * Ook dit gebeurt onder de rij-lock: een bod dat op de laatste milliseconde
 * binnenkomt mag niet naast het sluiten door glippen.
 *
 * Geen winnaar of reserveprijs niet gehaald betekent status `unsold`, géén
 * order, en de kavel terug naar de verkoper (hfst. 6).
 */
final class CloseAuction
{
    public function handle(Auction $auction): ?Order
    {
        $announcement = null;

        $order = DB::transaction(function () use ($auction, &$announcement): ?Order {
            $locked = Auction::query()
                ->whereKey($auction->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Al gesloten: niets te doen. Dit gebeurt echt - het vangnet en de
            // ingeplande job kunnen elkaar overlappen.
            if ($locked->isClosed()) {
                return $locked->order()->first();
            }

            $locked->setRelation('lot', $locked->lot()->sole());

            $winningBid = Bid::query()
                ->where('auction_id', $locked->id)
                ->where('status', BidStatus::Winning)
                ->orderByDesc('sequence')
                ->first();

            $reserveMet = $winningBid !== null && (
                $locked->reserve_price_cents === null
                || $winningBid->amount_cents >= $locked->reserve_price_cents
            );

            $order = $reserveMet
                ? $this->createOrder($locked, $winningBid)
                : null;

            $this->recordOutcome($locked, $winningBid, $order, $reserveMet);

            $announcement = fn () => AuctionClosed::dispatch($locked->refresh(), $order);

            return $order;
        });

        if ($announcement !== null) {
            DB::afterCommit($announcement);
        }

        return $order;
    }

    private function createOrder(Auction $auction, Bid $winningBid): Order
    {
        $lot = $auction->lot;

        /*
         | Het bedrag komt uit App\Support\Money - dezelfde klasse die de
         | kavelpagina gebruikte om de koper vóóraf te laten zien wat hij zou
         | betalen. Zouden die twee uiteenlopen, dan is dat per definitie een
         | bug en geen afronding (hfst. 9).
         |
         | Transport is hier nul: de koper regelt dat ná de hamerslag.
         */
        $costs = Money::forOrder($lot, $winningBid->amount_cents);

        return Order::create([
            'order_number' => 'AUC-'.str_pad((string) $auction->id, 6, '0', STR_PAD_LEFT),
            'lot_id' => $lot->id,
            'auction_id' => $auction->id,
            'buyer_id' => $winningBid->user_id,
            'seller_id' => $lot->seller_id,
            'hammer_cents' => $costs->hammerCents,
            'buyer_premium_cents' => $costs->buyerPremiumCents,
            'transport_cents' => $costs->transportCents,
            'vat_cents' => $costs->vatCents,
            'total_cents' => $costs->totalCents,
            'vat_regime' => $costs->vatRegime,
            'status' => OrderStatus::AwaitingPayment,
            'payment_deadline_at' => now()->addHours((int) config('aucvy.orders.payment_deadline_hours')),
        ]);
    }

    private function recordOutcome(Auction $auction, ?Bid $winningBid, ?Order $order, bool $reserveMet): void
    {
        $auction->closed_at = now();
        $auction->reserve_met = $reserveMet;

        if ($order !== null && $winningBid !== null) {
            $auction->winner_id = $winningBid->user_id;
            $auction->winning_bid_id = $winningBid->id;
        }

        $auction->last_sequence++;

        AuctionEvent::create([
            'auction_id' => $auction->id,
            'sequence' => $auction->last_sequence,
            'type' => AuctionEventType::Closed,
            'payload' => [
                'sold' => $order !== null,
                'reserve_met' => $reserveMet,
                'final_price_cents' => $auction->current_price_cents,
                'bid_count' => $auction->bid_count,
                'bidder_count' => $auction->bidder_count,
                'extension_count' => $auction->extension_count,
                'order_number' => $order?->order_number,
            ],
            'created_at' => now(),
        ]);

        $auction->save();

        $auction->lot->status = $order !== null ? LotStatus::Sold : LotStatus::Unsold;
        $auction->lot->save();

        // Losse maxima hebben geen betekenis meer zodra de veiling dicht is.
        $auction->autoBids()->where('active', true)->update([
            'active' => false,
            'cancelled_at' => now(),
        ]);

        /*
         | Borg terug naar iedereen die niet won - automatisch, direct bij het
         | sluiten (hfst. 9). De borg van de winnaar blijft staan en wordt
         | verrekend zodra de order betaald is (TransitionOrder::onPaid).
         */
        Deposit::query()
            ->where('auction_id', $auction->id)
            ->where('status', DepositStatus::Authorised)
            ->when($order !== null, fn ($query) => $query->where('user_id', '!=', $auction->winner_id))
            ->update(['status' => DepositStatus::Released, 'released_at' => now()]);
    }
}
