<?php

declare(strict_types=1);

namespace App\Lots\Moderation;

use App\Auctions\Events\AuctionClosed;
use App\Enums\AuctionEventType;
use App\Enums\DepositStatus;
use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\AuctionEvent;
use App\Models\AutoBid;
use App\Models\Bid;
use App\Models\Deposit;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\AuctionWithdrawn;
use App\Notifications\LotModerated;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Een kavel afwijzen: hij gaat offline met een reden. Een lopende veiling
 * sluit zonder winnaar en borgen gaan terug; de verkoper krijgt de reden
 * per mail. Alleen moderatie doet dit, met reden, en het staat in het
 * auditlog (briefing sectie 7: published naar rejected).
 */
final class RejectLot
{
    public function handle(Lot $lot, User $staff, string $reason): Lot
    {
        $closed = DB::transaction(function () use ($lot, $staff, $reason): ?object {
            $locked = Lot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ApproveLot::REVIEWABLE, true)) {
                throw ValidationException::withMessages(['lot' => __('moderation.errors.not_reviewable')]);
            }

            $auction = $locked->auction === null ? null : Auction::query()->whereKey($locked->auction->id)->lockForUpdate()->first();
            $wasOpen = $auction !== null && ! $auction->isClosed();

            if ($wasOpen) {
                // Dezelfde afsluiting als een gewone sluiting zonder winnaar: een
                // gebeurtenis met volgnummer, zodat de kavelpagina en de polling
                // hem oppikken, en geen automatische biedingen meer.
                $auction->closed_at = now();
                $auction->last_sequence++;
                $auction->save();

                AuctionEvent::create([
                    'auction_id' => $auction->id,
                    'sequence' => $auction->last_sequence,
                    'type' => AuctionEventType::Closed,
                    'payload' => [
                        'sold' => false,
                        'withdrawn' => true,
                        'reserve_met' => false,
                        'final_price_cents' => $auction->current_price_cents,
                        'bid_count' => $auction->bid_count,
                        'bidder_count' => $auction->bidder_count,
                        'extension_count' => $auction->extension_count,
                        'order_number' => null,
                    ],
                    'created_at' => now(),
                ]);

                AutoBid::query()->where('auction_id', $auction->id)->update(['active' => false]);

                Deposit::query()
                    ->where('auction_id', $auction->id)
                    ->where('status', DepositStatus::Authorised)
                    ->update(['status' => DepositStatus::Released, 'released_at' => now()]);
            }

            $before = $locked->status;
            $locked->status = LotStatus::Cancelled;
            $locked->reviewed_at = now();
            $locked->reviewed_by = $staff->id;
            $locked->moderation_note = $reason;
            $locked->save();

            Audit::record('lot.rejected', $locked, $staff, ['status' => $before->value], [
                'status' => LotStatus::Cancelled->value,
                'reason' => $reason,
                'auction_closed' => $wasOpen,
            ]);

            return $wasOpen ? $auction : null;
        });

        if ($closed !== null) {
            AuctionClosed::dispatch($closed->refresh(), null);

            // Iedereen die bood hoort dat de veiling zonder winnaar is beëindigd.
            User::query()
                ->whereIn('id', Bid::query()->where('auction_id', $closed->id)->distinct()->pluck('user_id'))
                ->get()
                ->each(fn (User $bidder) => $bidder->notify(new AuctionWithdrawn($closed)));
        }

        $lot->refresh();
        $lot->seller?->notify(new LotModerated($lot, approved: false, reason: $reason));

        return $lot;
    }
}
