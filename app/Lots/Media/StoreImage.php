<?php

declare(strict_types=1);

namespace App\Lots\Media;

use App\Enums\MediaType;
use App\Models\Lot;
use App\Models\LotMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Een afbeelding van schijf als foto bij een kavel zetten: controle op
 * inhoud (niet op extensie), opslag op de publieke disk, en de rij in
 * `lot_media` zoals de gewone upload die ook maakt.
 */
final class StoreImage
{
    public const MAX_PER_LOT = 20;

    public const MAX_BYTES = 10 * 1024 * 1024;

    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    /**
     * Een concept voor meerdere objecten of een inventaris bevat nog de foto's
     * van alle producten samen; Ivy verdeelt ze daarna over kavels van elk
     * hooguit twintig (SplitDraft).
     */
    public const MAX_PER_INVENTORY = 60;

    public static function isInventory(Lot $lot): bool
    {
        return $lot->isDraft() && in_array($lot->draft_meta['scope'] ?? null, ['multiple', 'inventory'], true);
    }

    public static function maxFor(Lot $lot): int
    {
        return self::isInventory($lot) ? self::MAX_PER_INVENTORY : self::MAX_PER_LOT;
    }

    public static function limitMessage(Lot $lot): string
    {
        return self::isInventory($lot)
            ? 'Maximaal '.self::MAX_PER_INVENTORY.' foto\'s tegelijk. Laat Ivy deze eerst verdelen en begin daarna een nieuw concept voor de rest.'
            : 'Maximaal '.self::MAX_PER_LOT.' foto\'s per kavel.';
    }

    public function handle(Lot $lot, string $localPath): LotMedia
    {
        $mime = (string) (@mime_content_type($localPath) ?: '');
        $extension = self::EXTENSIONS[$mime] ?? null;
        $size = (int) filesize($localPath);

        if ($extension === null || @getimagesize($localPath) === false) {
            throw ValidationException::withMessages(['file' => 'Geen foto: alleen JPG, PNG of WebP.']);
        }

        if ($size > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Groter dan 10 MB.']);
        }

        if ($lot->media()->count() >= self::maxFor($lot)) {
            throw ValidationException::withMessages(['file' => self::limitMessage($lot)]);
        }

        $stored = 'lots/'.$lot->public_id.'/'.Str::random(40).'.'.$extension;
        Storage::disk('public')->put($stored, (string) file_get_contents($localPath));
        [$width, $height] = @getimagesize($localPath) ?: [null, null];

        return LotMedia::create([
            'lot_id' => $lot->id,
            'type' => MediaType::Image,
            'path' => Storage::disk('public')->url($stored),
            'variants' => ['original' => $stored],
            'width' => $width,
            'height' => $height,
            'position' => $lot->media()->count(),
            'checksum' => hash_file('sha256', $localPath),
            'size_bytes' => $size,
        ]);
    }
}
