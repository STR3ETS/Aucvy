<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\Location;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Support\Trust\TrustIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Het publieke verkopersprofiel (hfst. 13.6, masterbriefing 4.1).
 *
 * Alle cijfers komen uit echte tabellen: de sterrenverdeling over álle
 * sterren, het aantal afgeronde verkopen, het aantal geschillen, de
 * verkochte kavels. Wat niet gemeten wordt ("98% op tijd", reactietijd)
 * staat er níét - een verzonnen betrouwbaarheidscijfer is erger dan geen.
 * Gerealiseerde prijzen zijn niet openbaar zolang dat beleid niet vaststaat.
 */
final class SellerProfileController extends Controller
{
    public function __invoke(Request $request, User $user, TrustIndex $trustIndex): View
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

        $sold = Lot::query()->where('seller_id', $user->id)->where('status', LotStatus::Sold);
        $soldCategory = $request->query('categorie');

        return view('sellers.show', [
            'seller' => $user,
            'company' => $user->company,
            'region' => $this->region($user),
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
            'activeCount' => Lot::query()
                ->where('seller_id', $user->id)
                ->whereIn('status', [LotStatus::Live, LotStatus::Closing])
                ->count(),
            'liveLots' => Lot::query()
                ->where('seller_id', $user->id)
                ->where('status', LotStatus::Live)
                ->with(['media', 'auction', 'category', 'order'])
                ->latest('published_at')
                ->limit(6)
                ->get(),
            'soldCount' => (clone $sold)->count(),
            'soldCategory' => is_string($soldCategory) ? $soldCategory : null,
            'soldCategories' => Category::query()
                ->whereIn('id', (clone $sold)->select('category_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'soldLots' => (clone $sold)
                ->when(is_string($soldCategory), fn ($query) => $query->whereHas('category', fn ($category) => $category->where('slug', $soldCategory)))
                ->with(['media', 'auction', 'category'])
                ->latest('published_at')
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    /**
     * De regio op provincieniveau: de provincie waar de meeste kavels van
     * deze verkoper staan. Nooit een adres.
     */
    private function region(User $user): ?string
    {
        $province = Location::query()
            ->whereIn('id', Lot::query()->select('location_id')->where('seller_id', $user->id)->whereNotNull('location_id'))
            ->whereNotNull('province')
            ->selectRaw('province, count(*) as aantal')
            ->groupBy('province')
            ->orderByDesc('aantal')
            ->value('province');

        return is_string($province) && $province !== '' ? $province : null;
    }
}
