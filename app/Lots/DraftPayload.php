<?php

declare(strict_types=1);

namespace App\Lots;

use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\LotMedia;
use App\Models\User;

/**
 * De JSON-vorm van een concept.
 *
 * Eén vorm voor twee plekken: de eerste render van /verkopen krijgt hem als
 * prop, en elke autosave-respons levert exact hetzelfde terug. Zo kan de
 * funnel niet uit de pas lopen met wat de server ervan vindt.
 */
final class DraftPayload
{
    public function __construct(
        private readonly CompletenessScore $completeness,
        private readonly Preflight $preflight,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(?Lot $lot, ?User $user): array
    {
        if ($lot === null) {
            return [
                'draft' => null,
                'completeness' => ['score' => 0, 'label' => 'start', 'hint' => __('funnel.completeness.start'), 'missing' => []],
                'fields' => (object) [],
                'preflight' => ['ok' => false, 'blocking' => [], 'checks' => []],
                'logged_in' => $user !== null,
            ];
        }

        $lot->loadMissing(['media', 'documents']);

        return [
            'draft' => [
                'id' => $lot->id,
                'public_id' => $lot->public_id,
                'title' => $lot->title,
                'description' => $lot->description,
                'category_id' => $lot->category_id,
                'condition' => $lot->condition?->value,
                'brand' => $lot->brand,
                'model' => $lot->model,
                'year' => $lot->year,
                'quantity' => $lot->quantity,
                'unit' => $lot->unit,
                // De uitkomst van de laatste controle, als die de kavel terugstuurde.
                'screening' => $lot->screening,
                'pickup_only' => $lot->pickup_only,
                'viewing_available' => $lot->viewing_available,
                'transport_from_cents' => $lot->transport_from_cents,
                'value_min_cents' => $lot->value_min_cents,
                'value_max_cents' => $lot->value_max_cents,
                'meta' => $lot->draft_meta ?? (object) [],
                'specifications' => (object) collect($lot->specifications ?? [])
                    ->map(fn (mixed $entry): array => is_array($entry) ? $entry : ['value' => $entry, 'source' => 'ai'])
                    ->all(),
                'media' => $lot->media->sortBy('position')->values()->map(fn (LotMedia $media): array => [
                    'id' => $media->id,
                    'url' => $media->path,
                    'position' => (int) $media->position,
                ])->all(),
                'documents' => $lot->documents->map(fn (LotDocument $document): array => [
                    'id' => $document->id,
                    'name' => $document->original_filename,
                    'kind' => $document->kind->value,
                ])->all(),
            ],
            'completeness' => $this->completeness->for($lot),
            'fields' => (object) Provenance::describe($lot),
            'preflight' => $this->preflight->for($lot, $user),
            'logged_in' => $user !== null,
        ];
    }
}
