<?php

declare(strict_types=1);

namespace App\Lots;

use App\Enums\LotStatus;
use App\Models\Category;
use App\Models\Lot;
use Illuminate\Support\Collection;

/**
 * Het aantal kavels per categorie dat nu te koop staat of is aangekondigd:
 * dezelfde statussen als de lijst op de veilingenpagina, zodat het getal op
 * een kaart klopt met wat je na de klik ziet. Een hoofdcategorie telt zijn
 * subcategorieën mee in het totaal.
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
            ->whereIn('status', [LotStatus::Scheduled, LotStatus::Live, LotStatus::Closing])
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
