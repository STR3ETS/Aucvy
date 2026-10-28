<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Vangnet voor paden zonder route: bestaat er een doorverwijzing, dan die;
 * anders de gewone 404. Draait alleen als geen enkele route paste, dus de
 * opzoeking kost niets op de pagina's zelf.
 */
final class RedirectFallbackController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $redirect = Redirect::forPath('/'.$request->path());

        if ($redirect === null) {
            abort(404);
        }

        // Tellen zonder de cache ongeldig te maken: alleen de kolommen,
        // niet het model (dat zou `saved` afvuren).
        Redirect::query()->whereKey($redirect['id'])->update([
            'hits' => DB::raw('hits + 1'),
            'last_hit_at' => now(),
        ]);

        return redirect()->to($redirect['to_url'], $redirect['status']);
    }
}
