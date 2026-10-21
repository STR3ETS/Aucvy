<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DocumentKind;
use App\Enums\LotStatus;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Lots\CompletenessScore;
use App\Lots\DraftAccess;
use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\LotMedia;
use App\Support\Audit\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Foto's en documenten van een concept, en de foto's van een kavel die al
 * live is (alleen de eigenaar). Elke wijziging aan een gepubliceerde kavel
 * laat een auditregel na.
 */
final class DraftUploadController extends Controller
{
    public function __construct(
        private readonly DraftAccess $access,
        private readonly CompletenessScore $completeness,
    ) {}

    public function storeMedia(Request $request, Lot $lot): JsonResponse
    {
        $lot = $this->access->resolveForMedia($request, $lot);

        $request->validate([
            // De `image`-regel kijkt naar de inhoud, niet naar de extensie;
            // `mimes` beperkt tot wat de wizard belooft en de AI-API aanneemt
            // (geen heic, bmp of avif).
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ]);

        if ($lot->media()->count() >= 20) {
            return response()->json(['message' => 'Maximaal 20 foto\'s per kavel.'], 422);
        }

        $file = $request->file('file');
        $path = $file->store('lots/'.$lot->public_id, 'public');
        [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];

        $media = LotMedia::create([
            'lot_id' => $lot->id,
            'type' => MediaType::Image,
            // De publieke URL, zodat de kaart en de galerij hem direct kunnen
            // tonen. Bij de S3-overstap wordt dit de variant-URL.
            'path' => Storage::disk('public')->url($path),
            'variants' => ['original' => $path],
            'width' => $width,
            'height' => $height,
            'position' => $lot->media()->count(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'size_bytes' => $file->getSize(),
        ]);

        $this->auditIfPublished($request, $lot, 'added', $media);

        return response()->json([
            'media' => $this->mediaPayload($media),
            'all' => $this->allMedia($lot),
            'completeness' => $this->completeness->for($lot->fresh()),
        ], 201);
    }

    public function destroyMedia(Request $request, Lot $lot, LotMedia $media): JsonResponse
    {
        $lot = $this->access->resolveForMedia($request, $lot);

        if ($media->lot_id !== $lot->id) {
            abort(404);
        }

        if (isset($media->variants['original'])) {
            Storage::disk('public')->delete($media->variants['original']);
        }

        $media->delete();
        $this->renumber($lot);
        $this->auditIfPublished($request, $lot, 'removed', $media);

        return response()->json([
            'all' => $this->allMedia($lot),
            'completeness' => $this->completeness->for($lot->fresh()),
        ]);
    }

    /**
     * Omslagfoto: deze gaat naar voren, de rest schuift op. De volgorde is
     * de positie; de eerste is wat kopers in de lijst zien.
     */
    public function cover(Request $request, Lot $lot, LotMedia $media): JsonResponse
    {
        $lot = $this->access->resolveForMedia($request, $lot);

        if ($media->lot_id !== $lot->id) {
            abort(404);
        }

        $ordered = $lot->media()->orderBy('position')->get()
            ->reject(fn (LotMedia $item): bool => $item->id === $media->id)
            ->prepend($media);

        foreach ($ordered->values() as $position => $item) {
            if ($item->position !== $position) {
                $item->forceFill(['position' => $position])->save();
            }
        }

        $this->auditIfPublished($request, $lot, 'cover', $media);

        return response()->json([
            'all' => $this->allMedia($lot),
            'completeness' => $this->completeness->for($lot->fresh()),
        ]);
    }

    public function storeDocument(Request $request, Lot $lot): JsonResponse
    {
        $lot = $this->access->resolve($request, $lot);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:15360'],
            'kind' => ['required', Rule::enum(DocumentKind::class)],
        ]);

        if ($lot->documents()->count() >= 15) {
            return response()->json(['message' => 'Maximaal 15 documenten per kavel.'], 422);
        }

        $file = $request->file('file');

        $document = LotDocument::create([
            'lot_id' => $lot->id,
            'kind' => $validated['kind'],
            // Private disk: een eigendomsbewijs hoort nooit achter een raadbare
            // URL. Inzien gaat straks via een kortlevende signed URL.
            'path' => $file->store('documents/'.$lot->public_id, 'local'),
            'original_filename' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return response()->json([
            'document' => [
                'id' => $document->id,
                'name' => $document->original_filename,
                'kind' => $document->kind->value,
            ],
            'completeness' => $this->completeness->for($lot->fresh()),
        ], 201);
    }

    /**
     * @return array{id: int, url: string, position: int}
     */
    private function mediaPayload(LotMedia $media): array
    {
        return ['id' => $media->id, 'url' => $media->path, 'position' => (int) $media->position];
    }

    /**
     * @return list<array{id: int, url: string, position: int}>
     */
    private function allMedia(Lot $lot): array
    {
        return $lot->media()->orderBy('position')->get()->map(fn (LotMedia $media): array => $this->mediaPayload($media))->all();
    }

    private function renumber(Lot $lot): void
    {
        foreach ($lot->media()->orderBy('position')->get()->values() as $position => $item) {
            if ($item->position !== $position) {
                $item->forceFill(['position' => $position])->save();
            }
        }
    }

    private function auditIfPublished(Request $request, Lot $lot, string $change, LotMedia $media): void
    {
        if ($lot->status === LotStatus::Draft || $request->user() === null) {
            return;
        }

        Audit::record('lot.media_changed', $lot, $request->user(), after: [
            'change' => $change,
            'media_id' => $media->id,
            'count' => $lot->media()->count(),
        ], ip: $request->ip());
    }
}
