<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dashboard\QuickSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Het zoekvenster van het dashboard (Ctrl K): geeft de treffers per groep
 * terug voor wat de klant typt.
 */
final class DashboardSearchController extends Controller
{
    public function __invoke(Request $request, QuickSearch $search): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:200']]);

        return response()->json($search->for($request->user(), (string) ($validated['q'] ?? '')));
    }
}
