<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Models\Dispute;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Support\Trust\TrustIndex;
use Illuminate\View\View;

/**
 * Het publieke verkopersprofiel (hfst. 13.6).
 *
 * Alle cijfers komen uit echte tabellen: de sterrenverdeling over álle
 * sterren, het aantal afgeronde verkopen, het aantal geschillen. Wat niet
 * gemeten wordt ("98% op tijd", reactietijd) staat er níét - een verzonnen
 * betrouwbaarheidscijfer is erger dan geen.
 */
final class SellerProfileController extends Controller
{
    public function __invoke(User $user, TrustIndex $trustIndex): View
    {
        $reviews = Review::query()
            ->where('seller_id', $user->id)
            ->whereNotNull('published_at')
            ->with('reviewer')
            ->latest('published_at')
            ->get();

        $trust = $trustIndex->forSeller($user);

        return view('sellers.show', [
            'seller' => $user,
            'company' => $user->company,
            'trust' => $trust,
            // De verloopgrafiek alleen als er ook een score ís.
            'trustHistory' => $trust !== null ? $trustIndex->historyForSeller($user) : [],
            'reviews' => $reviews,
            'averageRating' => $reviews->isEmpty() ? null : round($reviews->avg('rating'), 1),
            // Alle vijf de sterren, ook waar nul staat - dat is het punt.
            'distribution' => collect(range(5, 1))
                ->mapWithKeys(fn (int $stars) => [$stars => $reviews->where('rating', $stars)->count()]),
            'completedSales' => Order::query()
                ->where('seller_id', $user->id)
                ->where('status', OrderStatus::Completed)
                ->count(),
            'disputeCount' => Dispute::query()
                ->whereHas('order', fn ($query) => $query->where('seller_id', $user->id))
                ->count(),
            'liveLots' => Lot::query()
                ->where('seller_id', $user->id)
                ->where('status', LotStatus::Live)
                ->with(['media', 'auction', 'category'])
                ->latest('published_at')
                ->limit(6)
                ->get(),
        ]);
    }
}
