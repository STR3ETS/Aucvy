<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\AuctionEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuctionEventController extends Controller
{
    /**
     * Replay vanaf een volgnummer.
     *
     * Dit is wat een client na een netwerkdip weer op de goede stand krijgt:
     * "geef me alles na 431". Twee events in dezelfde milliseconde zijn niet op
     * tijd te ordenen - vandaar de teller (hfst. 5).
     *
     * Meer dan het maximum betekent voor de client: volledig herladen. Dan is
     * er zoveel gebeurd dat stap voor stap bijwerken langer duurt dan opnieuw
     * beginnen, en loopt hij het risico tussendoor op een oude stand te bieden.
     */
    public function index(Request $request, Auction $auction): JsonResponse
    {
        $after = max(0, $request->integer('after'));
        $limit = (int) config('aucvy.auction.max_replay_events');

        $events = AuctionEvent::query()
            ->where('auction_id', $auction->id)
            ->afterSequence($after)
            // Eentje extra ophalen om te wéten of er meer is, zonder een
            // tweede count-query.
            ->limit($limit + 1)
            ->get();

        $hasMore = $events->count() > $limit;

        return response()->json([
            'after' => $after,
            'current_sequence' => $auction->last_sequence,
            'reload_required' => $hasMore,
            'events' => $hasMore ? [] : $events->map(fn (AuctionEvent $event): array => [
                'sequence' => $event->sequence,
                'type' => $event->type->value,
                'payload' => $event->payload,
                'created_at' => $event->created_at->toIso8601String(),
            ])->all(),
        ]);
    }
}
