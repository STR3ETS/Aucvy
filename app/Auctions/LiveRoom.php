<?php

declare(strict_types=1);

namespace App\Auctions;

use App\Enums\AuctionEventType;
use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\AuctionEvent;
use App\Models\Bid;
use App\Support\Money\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * De live control room (briefing sectie 7): alle lopende veilingen op één
 * scherm, met wat er de laatste minuten gebeurde en wat aandacht vraagt.
 * Leest alleen; ingrijpen gaat via de kavelpagina in de backoffice.
 *
 * @phpstan-type Snapshot array{
 *   at: CarbonInterface,
 *   stats: array<string, int>,
 *   auctions: Collection<int, array<string, mixed>>,
 *   bids: Collection<int, array<string, mixed>>
 * }
 */
final class LiveRoom
{
    /**
     * @return Snapshot
     */
    public function snapshot(): array
    {
        $now = now();

        $auctions = Auction::query()
            ->whereNull('closed_at')
            ->whereHas('lot', fn ($query) => $query->whereIn('status', [LotStatus::Live, LotStatus::Closing, LotStatus::Paused]))
            ->with('lot:id,title,public_id,slug,status')
            ->orderBy('ends_at')
            ->limit(80)
            ->get()
            ->map(fn (Auction $auction): array => [
                'id' => $auction->id,
                'title' => $auction->lot->title,
                'public_id' => $auction->lot->public_id,
                'url' => route('admin.lots.show', $auction->lot),
                'public_url' => route('lots.show', $auction->lot),
                'price' => Money::format($auction->current_price_cents),
                'bids' => $auction->bid_count,
                'bidders' => $auction->bidder_count,
                'ends_at' => $auction->ends_at,
                'seconds_left' => (int) max(0, $now->diffInSeconds($auction->ends_at, false)),
                'extensions' => $auction->extension_count,
                'in_window' => $auction->paused_at === null && $auction->isInAntiSnipeWindow(),
                'paused' => $auction->paused_at !== null,
                'overdue' => $auction->paused_at === null && $auction->ends_at->lt($now),
                'reserve_met' => (bool) $auction->reserve_met,
            ]);

        $stats = [
            'live' => $auctions->where('paused', false)->count(),
            'paused' => $auctions->where('paused', true)->count(),
            'closing_hour' => $auctions->filter(fn (array $row): bool => ! $row['paused'] && $row['seconds_left'] <= 3600)->count(),
            'bids_5m' => Bid::query()->where('placed_at', '>=', $now->copy()->subMinutes(5))->count(),
            'bids_1h' => Bid::query()->where('placed_at', '>=', $now->copy()->subHour())->count(),
            'bidders_15m' => Bid::query()->where('placed_at', '>=', $now->copy()->subMinutes(15))->distinct()->count('user_id'),
            'extensions_1h' => AuctionEvent::query()->where('type', AuctionEventType::Extended->value)->where('created_at', '>=', $now->copy()->subHour())->count(),
            'overdue' => $auctions->where('overdue', true)->count(),
            'realtime_lag' => DB::table('jobs')->where('queue', 'realtime')->whereNull('reserved_at')->where('available_at', '<', $now->copy()->subMinute()->timestamp)->count(),
        ];

        $bids = Bid::query()
            ->with(['user' => fn ($query) => $query->withTrashed()->select(['id', 'name']), 'auction.lot:id,title,public_id'])
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (Bid $bid): array => [
                'at' => $bid->placed_at ?? $bid->created_at,
                'name' => $bid->user?->name ?? 'Verwijderde gebruiker',
                'amount' => Money::format($bid->amount_cents),
                'title' => $bid->auction->lot->title,
                'url' => route('admin.lots.show', $bid->auction->lot),
            ]);

        return ['at' => $now, 'stats' => $stats, 'auctions' => $auctions, 'bids' => $bids];
    }
}
