<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Lots\CategoryCounts;
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
    public function __invoke(CategoryCounts $categoryCounts): View
    {
        $roots = Category::roots()->with('children')->get();

        $counts = $categoryCounts->perCategory();

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

        $totals = $categoryCounts->perRoot($roots, $counts);

        return view('categories.index', [
            'roots' => $roots,
            'counts' => $counts,
            'totals' => $totals,
            'covers' => $covers,
        ]);
    }
}
