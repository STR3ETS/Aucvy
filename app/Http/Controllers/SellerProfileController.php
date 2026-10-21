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
use Illuminate\Support\Facades\Cache;
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
        // Geen profiel voor wie nooit iets aangeboden heeft: anders is
        // /verkoper/1..n een lijst van alle accounts met hun KYC-status.
        abort_unless($user->hasSellerProfile(), 404);

        $published = Review::query()->where('seller_id', $user->id)->whereNotNull('published_at');

        // Verdeling en gemiddelde als aggregaat; de lijst zelf begrensd. Een
        // publiek profiel op een opsombaar id mag geen duizend rijen laden.
        $perRating = (clone $published)->selectRaw('rating, count(*) as aantal')->groupBy('rating')->pluck('aantal', 'rating');
        $reviewCount = (int) $perRating->sum();
        $reviews = (clone $published)->with('reviewer')->latest('published_at')->limit(50)->get();

        // Vijf aggregaties per bezoek is te duur voor een publieke pagina
        // op een opsombaar id; het cijfer beweegt toch traag.
        $trust = Cache::remember(
            "trust:profile:{$user->id}",
            now()->addMinutes(10),
            fn (): array => ['trust' => $trustIndex->forSeller($user)],
        )['trust'];
        // De kavelpagina hergebruikt hetzelfde cijfer onder trust:{id}.

        return view('sellers.show', [
            'seller' => $user,
            'company' => $user->company,
            'trust' => $trust,
            'reviews' => $reviews,
            'averageRating' => $reviewCount === 0 ? null : round((float) (clone $published)->avg('rating'), 1),
            // Alle vijf de sterren, ook waar nul staat - dat is het punt.
            'distribution' => collect(range(5, 1))
                ->mapWithKeys(fn (int $stars) => [$stars => (int) $perRating->get($stars, 0)]),
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
