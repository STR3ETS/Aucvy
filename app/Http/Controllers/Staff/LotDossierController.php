<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Lots\Dossier\Dossier;
use App\Models\Lot;
use App\Models\LotActivity;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Het volledige kaveldossier voor beheer: elke regel, ongeacht
 * zichtbaarheid, met een filter per bron.
 */
final class LotDossierController extends Controller
{
    public function show(Request $request, Lot $lot): View
    {
        $source = (string) $request->query('bron', '');
        $source = in_array($source, LotActivity::SOURCES, true) ? $source : null;

        return view('admin.lots.dossier', [
            'lot' => $lot->load(['seller', 'company', 'order']),
            'source' => $source,
            'activities' => Dossier::timeline($lot, 'staff', $request->user(), $source, limit: 500),
            'counts' => LotActivity::query()->where('lot_id', $lot->id)
                ->selectRaw('source, count(*) as aantal')->groupBy('source')->pluck('aantal', 'source'),
        ]);
    }
}
