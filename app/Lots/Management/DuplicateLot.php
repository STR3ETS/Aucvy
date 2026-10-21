<?php

declare(strict_types=1);

namespace App\Lots\Management;

use App\Enums\LotStatus;
use App\Lots\PublicId;
use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\LotMedia;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Een kavel dupliceren of opnieuw plaatsen (briefing sectie 4): een nieuw
 * concept met dezelfde inhoud, foto's, documenten en verkoopinstellingen.
 * De verkoper loopt de wizard nog langs en publiceert zelf; de oorspronkelijke
 * kavel blijft als historie staan.
 */
final class DuplicateLot
{
    public function handle(Lot $source, User $seller, bool $relist = false): Lot
    {
        return DB::transaction(function () use ($source, $seller, $relist): Lot {
            $source->loadMissing(['auction', 'media', 'documents']);

            $copy = Lot::create([
                'public_id' => PublicId::fresh(),
                'seller_id' => $seller->id,
                'company_id' => $seller->company_id,
                'category_id' => $source->category_id,
                'location_id' => $source->location_id,
                'title' => $source->title,
                'description' => $source->description,
                'brand' => $source->brand,
                'model' => $source->model,
                'year' => $source->year,
                'quantity' => $source->quantity,
                'condition' => $source->condition,
                'specifications' => $source->specifications,
                'status' => LotStatus::Draft,
                'sale_type' => $source->sale_type,
                'vat_regime' => $source->vat_regime,
                'pickup_only' => (bool) $source->pickup_only,
                'viewing_available' => (bool) $source->viewing_available,
                'transport_from_cents' => $source->transport_from_cents,
                'draft_token' => Str::random(48),
                'draft_meta' => $this->saleSettings($source),
                // Wat al bevestigd en gepubliceerd was, hoeft niet opnieuw.
                'field_sources' => collect($source->field_sources ?? [])
                    ->map(fn (array $info): array => [...$info, 'needs_confirmation' => false])
                    ->all() ?: null,
            ]);

            // Eigen bestanden: een foto verwijderen bij de kopie mag het origineel niet raken.
            $source->media->each(fn (LotMedia $media) => LotMedia::create([
                'lot_id' => $copy->id,
                'type' => $media->type,
                ...$this->copyImage($media, $copy),
                'width' => $media->width,
                'height' => $media->height,
                'position' => $media->position,
                'alt' => $media->alt,
                'checksum' => $media->checksum,
                'size_bytes' => $media->size_bytes,
            ]));

            $source->documents->each(fn (LotDocument $document) => LotDocument::create([
                'lot_id' => $copy->id,
                'kind' => $document->kind,
                'path' => $this->copyDocument($document, $copy),
                'original_filename' => $document->original_filename,
                'mime_type' => $document->mime_type,
                'size_bytes' => $document->size_bytes,
            ]));

            Audit::record($relist ? 'lot.relisted' : 'lot.duplicated', $copy, $seller, after: [
                'source_id' => $source->id,
                'source_public_id' => $source->public_id,
            ]);

            return $copy;
        });
    }

    /**
     * Kopieert het beeldbestand naar de map van de kopie; lukt dat niet (bestand
     * weg), dan wijst de kopie naar hetzelfde bestand als voorheen.
     *
     * @return array{path: string, variants: array<string, string>|null}
     */
    private function copyImage(LotMedia $media, Lot $copy): array
    {
        $original = $media->variants['original'] ?? null;
        $disk = Storage::disk('public');

        if ($original === null || ! $disk->exists($original)) {
            return ['path' => $media->path, 'variants' => $media->variants];
        }

        $target = 'lots/'.$copy->public_id.'/'.Str::random(40).'.'.pathinfo($original, PATHINFO_EXTENSION);
        $disk->copy($original, $target);

        return ['path' => $disk->url($target), 'variants' => [...($media->variants ?? []), 'original' => $target]];
    }

    private function copyDocument(LotDocument $document, Lot $copy): string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($document->path)) {
            return $document->path;
        }

        $target = 'documents/'.$copy->public_id.'/'.Str::random(40).'.'.pathinfo($document->path, PATHINFO_EXTENSION);
        $disk->copy($document->path, $target);

        return $target;
    }

    /**
     * De verkoopinstellingen van de bron terug in de wachtkamer van het
     * concept, zodat stap 5 van de wizard al ingevuld is.
     *
     * @return array<string, mixed>
     */
    private function saleSettings(Lot $source): array
    {
        $auction = $source->auction;

        if ($auction === null) {
            return ($source->draft_meta ?? []) + ['ownership_declared' => true];
        }

        return array_filter([
            'sale_type' => $source->sale_type?->value,
            'start_price_cents' => $auction->start_price_cents ?: null,
            'reserve_price_cents' => $auction->reserve_price_cents,
            'buy_now_price_cents' => $auction->buy_now_price_cents,
            'dutch_start_cents' => $auction->dutch_start_cents,
            'dutch_floor_cents' => $auction->dutch_floor_cents,
            'dutch_step_cents' => $auction->dutch_step_cents,
            'dutch_interval_s' => $auction->dutch_interval_s,
            'duration_days' => max(1, min(30, (int) round($auction->starts_at->diffInDays($auction->original_ends_at, absolute: true)))),
            // De bron was gepubliceerd, dus eigendom was toen al aangetoond.
            'ownership_declared' => true,
        ], fn (mixed $value): bool => $value !== null);
    }
}
