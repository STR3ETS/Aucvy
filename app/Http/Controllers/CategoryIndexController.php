<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Lot;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Het categorie-overzicht: de hoofdcategorieën met hun subcategorieën, het
 * aantal zichtbare kavels per categorie en een foto uit het aanbod.
 */
final class CategoryIndexController extends Controller
{
    public function __invoke(): View
    {
        $roots = Category::roots()->with('children')->get();

        // Eén telling voor alle categorieën; een hoofdcategorie telt zijn
        // subcategorieën mee in het totaal.
        $counts = Lot::query()
            ->public()
            ->selectRaw('category_id, count(*) as aantal')
            ->groupBy('category_id')
            ->pluck('aantal', 'category_id')
            ->map(fn (int|string $aantal): int => (int) $aantal);

        $rootOf = [];
        foreach ($roots as $root) {
            $rootOf[$root->id] = $root->id;
            foreach ($root->children as $child) {
                $rootOf[$child->id] = $root->id;
            }
        }

        // Per hoofdcategorie de nieuwste foto uit het aanbod, als die er is.
        // Dit laadt alle publieke kavels met hun foto's; tien minuten cache
        // houdt dat van de kritieke route af.
        $covers = Cache::remember('categories:covers', now()->addMinutes(10), function () use ($roots): array {
            $covers = [];

            // Per hoofdcategorie de nieuwste publieke kavel met een foto en
            // daarvan de omslag: twee kleine, geïndexeerde query's per
            // hoofdcategorie in plaats van alle kavels met al hun foto's.
            foreach ($roots as $root) {
                $categoryIds = [$root->id, ...$root->children->pluck('id')->all()];

                $cover = Lot::query()
                    ->public()
                    ->whereIn('category_id', $categoryIds)
                    ->whereHas('media')
                    ->latest('id')
                    ->first(['id']);

                if ($cover !== null) {
                    $covers[$root->id] = $cover->media()->orderBy('position')->value('path');
                }
            }

            return $covers;
        });

        $totals = $roots->mapWithKeys(fn (Category $root): array => [
            $root->id => $counts->get($root->id, 0)
                + $root->children->sum(fn (Category $child): int => $counts->get($child->id, 0)),
        ]);

        return view('categories.index', [
            'roots' => $roots,
            'counts' => $counts,
            'totals' => $totals,
            'covers' => $covers,
        ]);
    }
}
