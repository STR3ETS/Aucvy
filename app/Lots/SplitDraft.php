<?php

declare(strict_types=1);

namespace App\Lots;

use App\Enums\LotStatus;
use App\Models\Lot;
use App\Models\LotMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Eén concept met foto's van verschillende producten wordt een rij losse
 * concepten, één per product (meerdere objecten of een hele inventaris).
 *
 * De eerste groep blijft in dit concept; elke andere groep krijgt een nieuw
 * concept met dezelfde eigenaar en dezelfde instellingen
 * voor btw, ophalen en verkoopvorm; dezelfde cookie opent het (DraftAccess).
 * De foto's verhuizen mee. Documenten en
 * verklaringen blijven bij het eerste concept: die gaan over één object.
 * Alle concepten delen een `batch` in draft_meta, zodat de funnel ze bij
 * elkaar kan tonen.
 */
final class SplitDraft
{
    /** Wat elk nieuw concept van stap 1 en de verkoopvorm overneemt. */
    private const array SHARED_META = ['scope', 'intake', 'sale_type', 'duration_days'];

    /**
     * @param  list<list<int>>  $groups  Foto-id's per product; de eerste groep blijft hier.
     * @return list<Lot> De nieuwe concepten.
     */
    public function handle(Lot $lot, array $groups): array
    {
        abort_unless($lot->status === LotStatus::Draft, 404);

        $owned = $lot->media()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $named = array_merge(...array_map(fn (array $group): array => array_map('intval', $group), $groups));

        if (count($groups) < 2 || array_diff($named, $owned) !== [] || count($named) !== count(array_unique($named))) {
            throw ValidationException::withMessages(['groups' => 'Deze verdeling past niet bij de foto\'s van dit concept.']);
        }

        return DB::transaction(function () use ($lot, $groups): array {
            $meta = $lot->draft_meta ?? [];
            $meta['batch'] ??= (string) Str::uuid();
            $lot->forceFill(['draft_meta' => $meta])->save();

            $shared = array_intersect_key($meta, array_flip([...self::SHARED_META, 'batch']));

            // Een token is uniek; de cookie die dit concept opent, opent via zijn hash ook de nieuwe.
            if ($lot->draft_token !== null) {
                $shared['cookie'] = $meta['cookie'] ?? DraftAccess::cookieHash($lot->draft_token);
            }
            $created = [];

            foreach (array_slice($groups, 1) as $group) {
                $sibling = Lot::create([
                    'public_id' => PublicId::fresh(),
                    'seller_id' => $lot->seller_id,
                    'company_id' => $lot->company_id,
                    'location_id' => $lot->location_id,
                    'status' => LotStatus::Draft,
                    'draft_token' => Str::random(48),
                    'quantity' => 1,
                    'unit' => 'stuk',
                    'vat_regime' => $lot->vat_regime,
                    'pickup_only' => $lot->pickup_only,
                    'viewing_available' => $lot->viewing_available,
                    'transport_from_cents' => $lot->transport_from_cents,
                    'draft_meta' => $shared,
                ]);

                foreach (array_values($group) as $position => $mediaId) {
                    LotMedia::query()->whereKey($mediaId)->update(['lot_id' => $sibling->id, 'position' => $position]);
                }

                $created[] = $sibling;
            }

            // De foto's die achterblijven sluiten weer aan, de hoofdfoto vooraan.
            foreach ($lot->media()->pluck('id')->values() as $position => $mediaId) {
                LotMedia::query()->whereKey($mediaId)->update(['position' => $position]);
            }

            return $created;
        });
    }

    /**
     * De andere concepten uit dezelfde splitsing, van dezelfde eigenaar.
     *
     * @return list<array{id: int, public_id: string, title: ?string, cover: ?string, value_min_cents: ?int, value_max_cents: ?int}>
     */
    public static function siblingsOf(Lot $lot): array
    {
        $batch = $lot->draft_meta['batch'] ?? null;

        if (! is_string($batch)) {
            return [];
        }

        return Lot::query()
            ->where('status', LotStatus::Draft)
            ->whereKeyNot($lot->id)
            ->where('draft_meta->batch', $batch)
            // Dezelfde eigenaar; zonder account delen ze de batch, die alleen het concept zelf kent.
            ->when(
                $lot->seller_id !== null,
                fn ($query) => $query->where('seller_id', $lot->seller_id),
                fn ($query) => $query->whereNull('seller_id'),
            )
            ->with('media')
            ->orderBy('id')
            ->get()
            ->map(fn (Lot $sibling): array => [
                'id' => $sibling->id,
                'public_id' => $sibling->public_id,
                'title' => $sibling->title,
                'cover' => $sibling->media->first()?->path,
                'value_min_cents' => $sibling->value_min_cents,
                'value_max_cents' => $sibling->value_max_cents,
            ])
            ->all();
    }
}
