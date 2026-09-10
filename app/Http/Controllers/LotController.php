<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\SaleType;
use App\Enums\ViewingStatus;
use App\Http\Resources\AuctionStateResource;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Review;
use App\Models\Viewing;
use App\Models\WatchlistItem;
use App\Support\Money\Money;
use App\Support\Trust\TrustIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LotController extends Controller
{
    /**
     * De kavelpagina.
     *
     * De volledige veilingstand gaat als prop mee naar de view. De pagina is
     * daarmee meteen correct zónder websocket; die neemt het daarna over.
     * Belangrijk voor SEO en voor de eerste seconde op mobiel (hfst. 13.3).
     */
    public function show(Request $request, Lot $lot): View
    {
        // Een concept is nooit publiek, ook niet via een gedeelde URL.
        if (! $lot->status->isPublic()) {
            throw new NotFoundHttpException;
        }

        $lot->load(['seller', 'company', 'category.parent', 'location', 'media', 'documents']);

        $auction = $lot->auction()->first();

        /*
         | De verkoperskaart toont alleen wat echt gemeten wordt: gepubliceerde
         | reviews en afgeronde verkopen. Geen verzonnen vertrouwensscore
         | (hfst. 13.6).
         */
        $sellerReviews = Review::query()
            ->where('seller_id', $lot->seller_id)
            ->whereNotNull('published_at');

        $reviewCount = (clone $sellerReviews)->count();

        return view('lots.show', [
            'lot' => $lot,
            'auction' => $auction,
            'sellerStats' => [
                'average_rating' => $reviewCount > 0
                    ? round((float) $sellerReviews->avg('rating'), 1)
                    : null,
                'review_count' => $reviewCount,
                'completed_sales' => Order::query()
                    ->where('seller_id', $lot->seller_id)
                    ->where('status', OrderStatus::Completed)
                    ->count(),
                /*
                 | De Trust Index als chip op de verkoperskaart. Een uur
                 | gecached: het cijfer beweegt traag, de kavelpagina niet.
                 */
                'trust_score' => Cache::remember(
                    "trust:{$lot->seller_id}",
                    now()->addHour(),
                    fn (): array => ['score' => app(TrustIndex::class)->forSeller($lot->seller)['score'] ?? null],
                )['score'],
            ],
            'isWatching' => $request->user() !== null && WatchlistItem::query()
                ->where('user_id', $request->user()->id)
                ->where('lot_id', $lot->id)
                ->exists(),
            /*
             | De lopende bezichtigingsaanvraag van de kijker zelf: het
             | formulier verdwijnt dan en de status komt ervoor in de plaats.
             */
            'viewerViewing' => $request->user() !== null
                ? Viewing::query()
                    ->where('lot_id', $lot->id)
                    ->where('user_id', $request->user()->id)
                    ->whereIn('status', [ViewingStatus::Requested, ViewingStatus::Confirmed])
                    ->latest('id')
                    ->first()
                : null,
            'watcherCount' => WatchlistItem::query()->where('lot_id', $lot->id)->count(),
            'similarLots' => $this->similarLots($lot),
            /*
             | Bladeren met de pijltjestoetsen: de buurkavels op id-volgorde,
             | met wrap-around - na de laatste kom je weer bij de eerste.
             */
            'nextLot' => Lot::query()->public()->where('id', '>', $lot->id)->orderBy('id')->first(['id', 'slug'])
                ?? Lot::query()->public()->whereKeyNot($lot->id)->orderBy('id')->first(['id', 'slug']),
            'previousLot' => Lot::query()->public()->where('id', '<', $lot->id)->orderByDesc('id')->first(['id', 'slug'])
                ?? Lot::query()->public()->whereKeyNot($lot->id)->orderByDesc('id')->first(['id', 'slug']),
            'state' => $auction !== null
                ? AuctionStateResource::make($auction, $request->user())->toArray()
                : null,
            /*
             | De kostenopbouw uit dezelfde klasse die straks de order maakt.
             | Niet client-side narekenen (hfst. 13.3).
             */
            'costs' => $auction !== null
                ? Money::forOrder($lot, $this->indicativePriceCents($lot, $auction), $lot->transport_from_cents ?? 0)
                : null,
        ]);
    }

    /**
     * Vergelijkbare kavels: eerst dezelfde categorie, aangevuld met de nieuwste
     * andere kavels als dat er te weinig zijn. Nooit een lege sectie.
     *
     * @return Collection<int, Lot>
     */
    private function similarLots(Lot $lot): Collection
    {
        $relations = ['media', 'auction', 'category', 'location'];

        $similar = Lot::query()
            ->public()
            ->whereKeyNot($lot->id)
            ->where('category_id', $lot->category_id)
            ->with($relations)
            ->latest('published_at')
            ->limit(3)
            ->get();

        if ($similar->count() < 3) {
            $similar = $similar->concat(Lot::query()
                ->public()
                ->whereKeyNot($lot->id)
                ->whereNotIn('id', $similar->modelKeys())
                ->with($relations)
                ->latest('published_at')
                ->limit(3 - $similar->count())
                ->get());
        }

        return $similar;
    }

    /**
     * Het bedrag waarover de kostenindicatie gaat: de huidige stand bij een
     * veiling, de vaste prijs bij koop-direct.
     */
    private function indicativePriceCents(Lot $lot, $auction): int
    {
        if ($lot->sale_type === SaleType::BuyNow && $auction->buy_now_price_cents !== null) {
            return $auction->buy_now_price_cents;
        }

        return $auction->current_price_cents;
    }
}
