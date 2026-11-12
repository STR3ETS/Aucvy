<?php

declare(strict_types=1);

namespace App\Ivy;

/**
 * Een foto klaarmaken voor het model.
 *
 * Telefoonfoto's zijn vaak groter dan de 5 MB die de API per foto aanneemt;
 * die werden eerder stil overgeslagen, waarna Ivy alleen op de tekst kon
 * gokken. Het model kijkt toch niet scherper dan 1568 pixels aan de lange
 * kant, dus alles wat groter is wordt eerst verkleind tot een jpeg. Dat
 * scheelt ook tokens. Een foto die rechtop gemaakt is maar liggend is
 * opgeslagen, wordt rechtgezet waar de exif-extensie er is.
 */
final class ModelImage
{
    /** Wat de Messages API als foto accepteert. */
    private const array TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** Waar het model zelf ook naartoe schaalt. */
    private const int LONG_EDGE = 1568;

    /** Kleiner dan dit en niet te groot in pixels: ongewijzigd door. */
    private const int PASS_BYTES = 3 * 1024 * 1024;

    /**
     * @return array{mime: string, data: string}|null
     */
    public static function encode(string $path, int $longEdge = self::LONG_EDGE): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $mime = (string) (mime_content_type($path) ?: '');

        if (! in_array($mime, self::TYPES, true)) {
            return null;
        }

        [$width, $height] = @getimagesize($path) ?: [0, 0];
        $orientation = self::orientation($path, $mime);
        $fits = filesize($path) <= self::PASS_BYTES && max($width, $height) <= $longEdge;

        if ($fits && $orientation <= 1) {
            return ['mime' => $mime, 'data' => base64_encode((string) file_get_contents($path))];
        }

        $resized = self::resize($path, $orientation, $longEdge);

        return $resized === null ? null : ['mime' => 'image/jpeg', 'data' => base64_encode($resized)];
    }

    private static function resize(string $path, int $orientation, int $longEdge): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            return null;
        }

        $image = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        } ?: $image;

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $longEdge / max($width, $height));

        if ($scale < 1) {
            $image = imagescale($image, (int) round($width * $scale), (int) round($height * $scale)) ?: $image;
        }

        ob_start();
        imagejpeg($image, null, 85);

        return (string) ob_get_clean();
    }

    /** De exif-oriëntatie van een jpeg, 1 als die er niet is. */
    private static function orientation(string $path, string $mime): int
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($path);

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }
}
