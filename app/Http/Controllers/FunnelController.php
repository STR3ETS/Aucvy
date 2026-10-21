<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompanyPlan;
use App\Enums\LotStatus;
use App\Lots\DraftAccess;
use App\Lots\DraftPayload;
use App\Models\Category;
use App\Models\Lot;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class FunnelController extends Controller
{
    /**
     * De verkoopfunnel.
     *
     * Zonder account, zonder muur: een bezoeker die al een concept had - op
     * naam of op cookie - gaat verder waar hij was. Een nieuw concept ontstaat
     * pas bij de eerste invoer, niet bij het bekijken van de pagina; anders
     * laat elke nieuwsgierige bezoeker een lege rij achter.
     *
     * `?concept={id}` opent een specifiek concept (uit het conceptoverzicht of
     * vanaf een ander apparaat), `?nieuw=1` begint leeg naast de bestaande
     * concepten (briefing sectie 5: conceptoverzicht en hervatten).
     */
    public function __invoke(Request $request, DraftAccess $access, DraftPayload $payload): View
    {
        $user = $request->user();

        $current = match (true) {
            $request->boolean('nieuw') => null,
            $request->filled('concept') => $access->resolve($request, Lot::query()->findOrFail((int) $request->query('concept'))),
            default => $access->current($request),
        };

        $drafts = $user === null ? collect() : Lot::query()
            ->where('seller_id', $user->id)
            ->where('status', LotStatus::Draft)
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))
            ->latest('updated_at')
            ->get(['id', 'public_id', 'title', 'completeness_score', 'updated_at']);

        return view('sell.funnel', [
            'initial' => $payload->for($current, $user),
            'drafts' => $drafts,
            'categories' => Category::roots()->with('children')->get(),
            // Per categorie de kenmerken uit het schema (van de ouder als de
            // subcategorie er geen heeft), voor de editor in stap 4.
            'schemas' => Category::query()->with('parent')->get()
                ->mapWithKeys(fn (Category $category): array => [$category->id => $category->effectiveSpecificationsSchema()])
                ->all(),
            // De succesfee volgens het plan van de verkoper; het starttarief
            // voor wie (nog) geen bedrijf heeft (hfst. 9).
            'feePercentage' => (float) ($user?->company?->fee_percentage
                ?? CompanyPlan::Start->defaultFeePercentage()),
            // De noodrem (hfst. 8): staat Ivy uit, dan toont stap 3 de
            // handmatige route in plaats van een knop die niets gaat doen.
            'ivyEnabled' => (bool) config('aucvy.ai.enabled'),
        ]);
    }
}
