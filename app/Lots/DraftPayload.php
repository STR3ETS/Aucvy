<?php

declare(strict_types=1);

namespace App\Lots;

use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\LotMedia;

/**
 * De JSON-vorm van een concept.
 *
 * Eén vorm voor twee plekken: de eerste render van /verkopen krijgt hem als
 * prop, en elke autosave-respons levert exact hetzelfde terug. Zo kan de
 * funnel niet uit de pas lopen met wat de server ervan vindt.
 */
final class DraftPayload
{
    public function __construct(private readonly CompletenessScore $completeness) {}

    /**
     * @return array<string, mixed>
     */
    public function for(?Lot $lot, bool $loggedIn): array
    {
        if ($lot === null) {
            return [
                'draft' => null,
                'completeness' => ['score' => 0, 'label' => 'start', 'hint' => __('funnel.completeness.start'), 'missing' => []],
                'logged_in' => $loggedIn,
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
                'pickup_only' => $lot->pickup_only,
                'viewing_available' => $lot->viewing_available,
                'transport_from_cents' => $lot->transport_from_cents,
                'meta' => $lot->draft_meta ?? (object) [],
                'media' => $lot->media->map(fn (LotMedia $media): array => [
                    'id' => $media->id,
                    'url' => $media->path,
                ])->all(),
                'documents' => $lot->documents->map(fn (LotDocument $document): array => [
                    'id' => $document->id,
                    'name' => $document->original_filename,
                    'kind' => $document->kind->value,
                ])->all(),
            ],
            'completeness' => $this->completeness->for($lot),
            'logged_in' => $loggedIn,
        ];
    }
}
