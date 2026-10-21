<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\LotStatus;
use App\Http\Controllers\Controller;
use App\Lots\CompletenessScore;
use App\Lots\DraftAccess;
use App\Lots\Media\StoreImage;
use App\Models\Lot;
use App\Models\LotMedia;
use App\Support\Audit\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Hervatbare upload in delen (briefing sectie 5, stap 1): een grote foto
 * gaat in stukken van een megabyte; valt de verbinding weg, dan gaat de
 * browser verder waar hij was. De delen staan op de private disk tot ze
 * samengevoegd zijn; dan maakt StoreImage er een gewone foto van.
 */
final class ChunkedUploadController extends Controller
{
    public const MAX_CHUNKS = 64;

    public const CHUNK_MAX_KB = 2048;

    public const MAX_OPEN_UPLOADS = 5;

    public function __construct(
        private readonly DraftAccess $access,
        private readonly CompletenessScore $completeness,
    ) {}

    public function start(Request $request, Lot $lot): JsonResponse
    {
        $lot = $this->access->resolveForMedia($request, $lot);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.StoreImage::MAX_BYTES],
            'type' => ['required', 'string', 'starts_with:image/'],
            'chunks' => ['required', 'integer', 'min:1', 'max:'.self::MAX_CHUNKS],
        ]);

        if ($lot->media()->count() >= StoreImage::MAX_PER_LOT) {
            return response()->json(['message' => 'Maximaal 20 foto\'s per kavel.'], 422);
        }

        if (count(Storage::disk('local')->directories('chunks/'.$lot->id)) >= self::MAX_OPEN_UPLOADS) {
            return response()->json(['message' => 'Te veel uploads tegelijk open; maak er eerst een af.'], 422);
        }

        $upload = (string) Str::uuid();

        Storage::disk('local')->put($this->dir($lot, $upload).'/meta.json', json_encode([
            'name' => $validated['name'],
            'size' => (int) $validated['size'],
            'type' => $validated['type'],
            'chunks' => (int) $validated['chunks'],
            'created_at' => now()->toIso8601String(),
        ]));

        return response()->json(['upload' => $upload, 'chunks' => (int) $validated['chunks'], 'received' => []], 201);
    }

    public function status(Request $request, Lot $lot, string $upload): JsonResponse
    {
        $lot = $this->access->resolveForMedia($request, $lot);
        $meta = $this->meta($lot, $upload);

        return response()->json(['upload' => $upload, 'chunks' => $meta['chunks'], 'received' => $this->received($lot, $upload, $meta['chunks'])]);
    }

    public function chunk(Request $request, Lot $lot, string $upload, int $index): JsonResponse
    {
        $lot = $this->access->resolveForMedia($request, $lot);
        $meta = $this->meta($lot, $upload);

        abort_if($index < 0 || $index >= $meta['chunks'], 404);

        $request->validate([
            'chunk' => ['required', 'file', 'max:'.self::CHUNK_MAX_KB],
        ]);

        $disk = Storage::disk('local');
        $disk->put($this->dir($lot, $upload).'/'.$index.'.part', (string) file_get_contents($request->file('chunk')->getRealPath()));

        // Meer bytes dan aangekondigd: weg ermee, de browser liegt of is in de war.
        $stored = array_sum(array_map(fn (string $file): int => (int) $disk->size($file), array_filter($disk->files($this->dir($lot, $upload)), fn (string $file): bool => str_ends_with($file, '.part'))));

        if ($stored > $meta['size']) {
            $disk->deleteDirectory($this->dir($lot, $upload));

            return response()->json(['message' => 'De upload is groter dan opgegeven; begin opnieuw.'], 422);
        }

        return response()->json(['upload' => $upload, 'chunks' => $meta['chunks'], 'received' => $this->received($lot, $upload, $meta['chunks'])]);
    }

    public function complete(Request $request, Lot $lot, string $upload, StoreImage $store): JsonResponse
    {
        $lot = $this->access->resolveForMedia($request, $lot);
        $meta = $this->meta($lot, $upload);

        // Twee keer "klaar" tegelijk mag niet twee foto's opleveren.
        $lock = Cache::lock('chunked-upload:'.$upload, 30);

        if (! $lock->get()) {
            return response()->json(['message' => 'Deze upload wordt al samengevoegd.'], 409);
        }

        try {
            return $this->assemble($request, $lot, $upload, $meta, $store);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{name: string, size: int, type: string, chunks: int}  $meta
     */
    private function assemble(Request $request, Lot $lot, string $upload, array $meta, StoreImage $store): JsonResponse
    {
        $received = $this->received($lot, $upload, $meta['chunks']);

        if (count($received) !== $meta['chunks']) {
            return response()->json([
                'message' => 'Nog niet alle delen zijn binnen.',
                'missing' => array_values(array_diff(range(0, $meta['chunks'] - 1), $received)),
            ], 422);
        }

        $disk = Storage::disk('local');
        $assembled = tempnam(sys_get_temp_dir(), 'aucvy-upload');
        $handle = fopen($assembled, 'wb');

        for ($index = 0; $index < $meta['chunks']; $index++) {
            fwrite($handle, (string) $disk->get($this->dir($lot, $upload).'/'.$index.'.part'));
        }

        fclose($handle);

        try {
            $media = $store->handle($lot, $assembled);
        } finally {
            @unlink($assembled);
            $disk->deleteDirectory($this->dir($lot, $upload));
        }

        if ($lot->status !== LotStatus::Draft) {
            Audit::record('lot.media_changed', $lot, $request->user(), after: ['change' => 'added', 'media_id' => $media->id], ip: $request->ip());
        }

        return response()->json([
            'media' => $this->payload($media),
            'all' => $lot->media()->orderBy('position')->get()->map(fn (LotMedia $item): array => $this->payload($item))->all(),
            'completeness' => $this->completeness->for($lot->fresh()),
        ], 201);
    }

    /**
     * @return array{name: string, size: int, type: string, chunks: int}
     */
    private function meta(Lot $lot, string $upload): array
    {
        abort_unless(Str::isUuid($upload), 404);

        $raw = Storage::disk('local')->get($this->dir($lot, $upload).'/meta.json');
        abort_if($raw === null, 404);

        $meta = json_decode((string) $raw, true);
        abort_unless(is_array($meta) && isset($meta['chunks']), 404);

        return ['name' => (string) $meta['name'], 'size' => (int) $meta['size'], 'type' => (string) $meta['type'], 'chunks' => (int) $meta['chunks']];
    }

    /**
     * @return list<int>
     */
    private function received(Lot $lot, string $upload, int $chunks): array
    {
        $disk = Storage::disk('local');
        $received = [];

        for ($index = 0; $index < $chunks; $index++) {
            if ($disk->exists($this->dir($lot, $upload).'/'.$index.'.part')) {
                $received[] = $index;
            }
        }

        return $received;
    }

    private function dir(Lot $lot, string $upload): string
    {
        return 'chunks/'.$lot->id.'/'.$upload;
    }

    /**
     * @return array{id: int, url: string, position: int}
     */
    private function payload(LotMedia $media): array
    {
        return ['id' => $media->id, 'url' => $media->path, 'position' => (int) $media->position];
    }
}
