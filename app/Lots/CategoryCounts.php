<?php

declare(strict_types=1);

namespace App\Lots;

use App\Models\Category;
use App\Models\Lot;
use Illuminate\Support\Collection;

/**
 * Het aantal zichtbare kavels per categorie, voor het categorie-overzicht en
 * de categoriekaarten op de home. Een hoofdcategorie telt zijn subcategorieën
 * mee in het totaal.
 */
final class CategoryCounts
{
    /**
     * Eén telling voor alle categorieën.
     *
     * @return Collection<int, int>
     */
    public function perCategory(): Collection
    {
        return Lot::query()
            ->public()
            ->selectRaw('category_id, count(*) as aantal')
            ->groupBy('category_id')
            ->pluck('aantal', 'category_id')
            ->map(fn (int|string $aantal): int => (int) $aantal);
    }

    /**
     * @param  Collection<int, Category>  $roots  met de subcategorieën geladen
     * @param  Collection<int, int>|null  $counts
     * @return Collection<int, int>
     */
    public function perRoot(Collection $roots, ?Collection $counts = null): Collection
    {
        $counts ??= $this->perCategory();

        return $roots->mapWithKeys(fn (Category $root): array => [
            $root->id => $counts->get($root->id, 0)
                + $root->children->sum(fn (Category $child): int => $counts->get($child->id, 0)),
        ]);
    }
}
