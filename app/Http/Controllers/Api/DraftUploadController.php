<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DocumentKind;
use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Lots\CompletenessScore;
use App\Lots\DraftAccess;
use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\LotMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Uploads voor een concept.
 *
 * De briefing schrijft presigned URL's naar S3 voor (hfst. 7). Lokaal is er
 * geen S3, dus hier landt het bestand via de server op een disk. De rest van
 * het contract is wél alvast zoals het hoort: het MIME-type wordt serverside
 * uit de inhoud bepaald en niet uit de bestandsnaam, de checksum wordt vanaf
 * dag één gevuld (dat is later de dubbele-fotodetectie), en documenten staan
 * op een private disk - nooit publiek (hfst. 15).
 */
final class DraftUploadController extends Controller
{
    public function __construct(
        private readonly DraftAccess $access,
        private readonly CompletenessScore $completeness,
    ) {}

    public function storeMedia(Request $request, Lot $lot): JsonResponse
    {
        $lot = $this->access->resolve($request, $lot);

        $request->validate([
            // De `image`-regel kijkt naar de inhoud, niet naar de extensie.
            'file' => ['required', 'image', 'max:10240'],
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

        return response()->json([
            'media' => ['id' => $media->id, 'url' => $media->path],
            'completeness' => $this->completeness->for($lot->fresh()),
        ], 201);
    }

    public function destroyMedia(Request $request, Lot $lot, LotMedia $media): JsonResponse
    {
        $lot = $this->access->resolve($request, $lot);

        if ($media->lot_id !== $lot->id) {
            abort(404);
        }

        if (isset($media->variants['original'])) {
            Storage::disk('public')->delete($media->variants['original']);
        }

        $media->delete();

        return response()->json([
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
}
