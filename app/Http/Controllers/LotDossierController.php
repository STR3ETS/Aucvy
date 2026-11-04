<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Distribution\ShareKit;
use App\Distribution\SocialPlanning;
use App\Lots\Dossier\Dossier;
use App\Models\Lot;
use App\Models\LotActivity;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Het kaveldossier voor de verkoper (briefing nieuwe functies): één pagina
 * per kavel met tabs Overzicht, Berichten, Documenten, Verspreiding en
 * Dossier. Alles wat met de kavel gebeurt, landt hier.
 */
final class LotDossierController extends Controller
{
    /** @var list<string> */
    public const TABS = ['overzicht', 'berichten', 'documenten', 'verspreiding', 'dossier'];

    public function show(Request $request, Lot $lot): View
    {
        abort_unless($lot->isManagedBy($request->user()), 404);

        $tab = (string) $request->query('tab', 'overzicht');
        $tab = in_array($tab, self::TABS, true) ? $tab : 'overzicht';
        $source = (string) $request->query('bron', '');

        $lot->load(['auction', 'category', 'media', 'documents', 'order', 'viewings']);

        return view('dashboard.lot-dossier', [
            'lot' => $lot,
            'tab' => $tab,
            'source' => in_array($source, LotActivity::SOURCES, true) ? $source : null,
            'activities' => Dossier::timeline($lot, 'seller', $request->user(), $tab === 'dossier' && $source !== '' ? $source : null, limit: $tab === 'dossier' ? 200 : 6),
            'counts' => LotActivity::query()->where('lot_id', $lot->id)->visibleTo('seller', $request->user())
                ->selectRaw('source, count(*) as aantal')->groupBy('source')->pluck('aantal', 'source'),
            'conversations' => $tab === 'berichten'
                ? $lot->conversations()->with(['buyer', 'company', 'seller'])->orderByRaw('last_message_at is null, last_message_at desc')->get()
                : collect(),
            // Verspreiding: de deelkit en de social planning worden aangemaakt zodra de tab opengaat.
            'kit' => $tab === 'verspreiding' && $lot->status->isPublic() ? app(ShareKit::class)->for($lot) : collect(),
            'planning' => $tab === 'verspreiding' ? ($lot->status->isPublic() ? app(SocialPlanning::class)->for($lot) : app(SocialPlanning::class)->planned($lot)) : collect(),
        ]);
    }
}
