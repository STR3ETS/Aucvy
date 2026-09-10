<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompanyPlan;
use App\Lots\DraftAccess;
use App\Lots\DraftPayload;
use App\Models\Category;
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
     */
    public function __invoke(Request $request, DraftAccess $access, DraftPayload $payload): View
    {
        return view('sell.funnel', [
            'initial' => $payload->for($access->current($request), $request->user() !== null),
            'categories' => Category::roots()->with('children')->get(),
            // De succesfee volgens het plan van de verkoper; het starttarief
            // voor wie (nog) geen bedrijf heeft (hfst. 9).
            'feePercentage' => (float) ($request->user()?->company?->fee_percentage
                ?? CompanyPlan::Start->defaultFeePercentage()),
            // De noodrem (hfst. 8): staat Ivy uit, dan toont stap 3 de
            // handmatige route in plaats van een knop die niets gaat doen.
            'ivyEnabled' => (bool) config('aucvy.ai.enabled'),
        ]);
    }
}
