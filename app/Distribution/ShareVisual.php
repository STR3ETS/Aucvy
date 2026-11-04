<?php

declare(strict_types=1);

namespace App\Distribution;

use App\Models\Lot;
use App\Models\LotDistribution;
use App\Support\Money\Money;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Het deelbeeld per kavel: de eerste foto, de titel, het bod en de sluiting,
 * in drie formaten (vierkant, staand, liggend). Het liggende beeld is ook
 * de Open Graph-afbeelding van de kavelpagina. Gemaakt met GD, zonder
 * extra pakket; het lettertype is Manrope (OFL) uit resources/fonts.
 */
final class ShareVisual
{
    public const string SQUARE = 'square';

    public const string PORTRAIT = 'portrait';

    public const string LANDSCAPE = 'landscape';

    public const FORMATS = [self::SQUARE, self::PORTRAIT, self::LANDSCAPE];

    /** @var array<string, array{0: int, 1: int}> */
    public const DIMENSIONS = [
        self::SQUARE => [1080, 1080],
        self::PORTRAIT => [1080, 1350],
        self::LANDSCAPE => [1200, 630],
    ];

    private const string DISK = 'public';

    /**
     * Maakt het beeld als het er nog niet is en geeft het pad op de
     * publieke schijf. De cache-sleutel verandert mee met titel, bod,
     * foto en sluiting, dus een oud beeld blijft nooit hangen.
     */
    public function render(Lot $lot, string $format = self::LANDSCAPE, string $moment = LotDistribution::KIT): string
    {
        $format = in_array($format, self::FORMATS, true) ? $format : self::LANDSCAPE;
        $lot->loadMissing(['auction', 'media', 'category']);

        $path = sprintf('share/%d/%s-%s-%s.jpg', $lot->id, $format, $moment, $this->fingerprint($lot, $moment));

        if (Storage::disk(self::DISK)->exists($path)) {
            return $path;
        }

        [$width, $height] = self::DIMENSIONS[$format];
        $canvas = imagecreatetruecolor($width, $height);
        $navy = imagecolorallocate($canvas, 12, 19, 38);
        imagefill($canvas, 0, 0, $navy);

        $photo = $this->photo($lot);
        $panel = $this->placePhoto($canvas, $photo, $format, $width, $height);
        $this->drawText($canvas, $lot, $moment, $format, $panel);

        ob_start();
        imagejpeg($canvas, null, 86);
        $bytes = (string) ob_get_clean();

        Storage::disk(self::DISK)->put($path, $bytes);

        // Oude varianten van dezelfde kavel opruimen; alleen de actuele blijft.
        foreach (Storage::disk(self::DISK)->files("share/{$lot->id}") as $old) {
            if ($old !== $path && Str::startsWith(basename($old), "{$format}-{$moment}-")) {
                Storage::disk(self::DISK)->delete($old);
            }
        }

        return $path;
    }

    public function absolutePath(string $path): string
    {
        return Storage::disk(self::DISK)->path($path);
    }

    public function url(Lot $lot, string $format = self::LANDSCAPE, string $moment = LotDistribution::KIT): string
    {
        return route('lots.share-image', ['lot' => $lot, 'format' => $format, 'moment' => $moment]);
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function dimensions(string $format): array
    {
        return self::DIMENSIONS[$format] ?? self::DIMENSIONS[self::LANDSCAPE];
    }

    private function fingerprint(Lot $lot, string $moment): string
    {
        return substr(hash('sha256', implode('|', [
            $lot->title,
            $lot->currentPriceCents(),
            $lot->auction?->bid_count,
            $lot->auction?->ends_at?->timestamp,
            $lot->media->first()?->path,
            $moment,
            'v1',
        ])), 0, 12);
    }

    /**
     * De eerste foto als GD-beeld; null als er geen (bereikbare) foto is.
     */
    private function photo(Lot $lot): ?GdImage
    {
        $source = $lot->media->first()?->path;

        if (! is_string($source) || $source === '') {
            return null;
        }

        $bytes = $this->bytesOf($source);

        if ($bytes === null) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        return $image === false ? null : $image;
    }

    private function bytesOf(string $source): ?string
    {
        if (Str::startsWith($source, ['http://', 'https://'])) {
            $local = parse_url($source, PHP_URL_PATH);

            if (is_string($local) && Str::startsWith($local, '/storage/') && Storage::disk(self::DISK)->exists(substr($local, 9))) {
                return Storage::disk(self::DISK)->get(substr($local, 9));
            }

            if (app()->runningUnitTests()) {
                return null;
            }

            $bytes = @file_get_contents($source, false, stream_context_create(['http' => ['timeout' => 5]]));

            return $bytes === false ? null : $bytes;
        }

        $relative = Str::startsWith($source, '/storage/') ? substr($source, 9) : ltrim($source, '/');

        if (Storage::disk(self::DISK)->exists($relative)) {
            return Storage::disk(self::DISK)->get($relative);
        }

        if (is_file(public_path($relative))) {
            return (string) file_get_contents(public_path($relative));
        }

        return null;
    }

    /**
     * Zet de foto bedekkend in zijn vak en geeft het tekstvak terug.
     *
     * @return array{x: int, y: int, w: int, h: int}
     */
    private function placePhoto(GdImage $canvas, ?GdImage $photo, string $format, int $width, int $height): array
    {
        $split = match ($format) {
            self::LANDSCAPE => 0.56,
            self::SQUARE => 0.52,
            default => 0.58,
        };
        $panel = $format === self::LANDSCAPE
            ? ['x' => (int) round($width * $split), 'y' => 0, 'w' => $width - (int) round($width * $split), 'h' => $height]
            : ['x' => 0, 'y' => (int) round($height * $split), 'w' => $width, 'h' => $height - (int) round($height * $split)];

        $box = $format === self::LANDSCAPE
            ? ['x' => 0, 'y' => 0, 'w' => $panel['x'], 'h' => $height]
            : ['x' => 0, 'y' => 0, 'w' => $width, 'h' => $panel['y']];

        if ($photo === null) {
            // Geen foto: een rustig blauw vlak met een lichte gloed.
            $blue = imagecolorallocate($canvas, 46, 92, 198);
            imagefilledrectangle($canvas, $box['x'], $box['y'], $box['x'] + $box['w'], $box['y'] + $box['h'], $blue);
            $glow = imagecolorallocatealpha($canvas, 255, 255, 255, 100);
            imagefilledellipse($canvas, $box['x'] + (int) ($box['w'] * 0.7), $box['y'] + (int) ($box['h'] * 0.3), (int) ($box['w'] * 0.9), (int) ($box['h'] * 0.9), $glow);

            return $panel;
        }

        $sourceWidth = imagesx($photo);
        $sourceHeight = imagesy($photo);
        $scale = max($box['w'] / $sourceWidth, $box['h'] / $sourceHeight);
        $cropWidth = (int) round($box['w'] / $scale);
        $cropHeight = (int) round($box['h'] / $scale);
        $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
        $cropY = (int) round(($sourceHeight - $cropHeight) / 2);

        imagecopyresampled($canvas, $photo, $box['x'], $box['y'], $cropX, $cropY, $box['w'], $box['h'], $cropWidth, $cropHeight);

        return $panel;
    }

    /**
     * @param  array{x: int, y: int, w: int, h: int}  $panel
     */
    private function drawText(GdImage $canvas, Lot $lot, string $moment, string $format, array $panel): void
    {
        $font = $this->font();
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $muted = imagecolorallocate($canvas, 170, 182, 204);
        $accent = imagecolorallocate($canvas, 255, 196, 61);

        $scale = $format === self::LANDSCAPE ? 1.0 : 1.2;
        $padding = (int) round(44 * $scale);
        $x = $panel['x'] + $padding;
        $maxWidth = $panel['w'] - 2 * $padding;
        $footerY = $panel['y'] + $panel['h'] - $padding;
        $y = $panel['y'] + $padding + (int) round(16 * $scale);

        // Puntgrootte naar pixels: een regel is ongeveer 1,35 keer de puntgrootte hoog.
        $eyebrowSize = (int) round(14 * $scale);
        $this->text($canvas, $font, $eyebrowSize, $x, $y, $accent, mb_strtoupper(ChannelTexts::momentEyebrow($moment)), true);
        $y += (int) round(($format === self::LANDSCAPE ? 30 : 36) * 1.35 * $scale);

        $titleSize = (int) round(($format === self::LANDSCAPE ? 30 : 36) * $scale);
        $titleLines = $this->wrap($font, $titleSize, (string) $lot->title, $maxWidth, $format === self::LANDSCAPE ? 3 : 2);
        foreach ($titleLines as $line) {
            $this->text($canvas, $font, $titleSize, $x, $y, $white, $line, true);
            $y += (int) round($titleSize * 1.3);
        }
        $y += (int) round(12 * $scale);

        if ($lot->auction !== null) {
            $labelSize = (int) round(14 * $scale);
            $priceSize = (int) round(36 * $scale);
            $closesSize = (int) round(14 * $scale);
            $this->text($canvas, $font, $labelSize, $x, $y, $muted, ChannelTexts::priceLabel($lot, $moment), false);
            $y += (int) round($priceSize * 1.35) + (int) round(6 * $scale);
            $this->text($canvas, $font, $priceSize, $x, $y, $white, Money::format($lot->currentPriceCents()), true);
            $y += (int) round($closesSize * 1.35) + (int) round(16 * $scale);

            if ($lot->auction->ends_at !== null && ! $lot->auction->isClosed() && $y < $footerY - (int) round(30 * $scale)) {
                $closes = 'Sluit '.$lot->auction->ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('dd D MMM, HH:mm');
                $this->text($canvas, $font, $closesSize, $x, $y, $muted, $closes, false);
            }
        }
        $this->text($canvas, $font, (int) round(16 * $scale), $x, $footerY, $white, 'aucvy.nl', true);

        $category = (string) ($lot->category?->name ?? '');

        if ($category !== '') {
            $categoryWidth = $this->textWidth($font, (int) round(13 * $scale), $category);
            $this->text($canvas, $font, (int) round(13 * $scale), $panel['x'] + $panel['w'] - $padding - $categoryWidth, $footerY, $muted, $category, false);
        }
    }

    private function text(GdImage $canvas, string $font, int $size, int $x, int $y, int $color, string $text, bool $bold): void
    {
        // Het variabele lettertype rendert in één gewicht; vet is twee keer tekenen met een pixel verschil.
        imagettftext($canvas, $size, 0, $x, $y, $color, $font, $text);

        if ($bold) {
            imagettftext($canvas, $size, 0, $x + 1, $y, $color, $font, $text);
            imagettftext($canvas, $size, 0, $x, $y + 1, $color, $font, $text);
        }
    }

    private function textWidth(string $font, int $size, string $text): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? 0 : abs($box[2] - $box[0]);
    }

    /**
     * @return list<string>
     */
    private function wrap(string $font, int $size, string $text, int $maxWidth, int $maxLines): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($this->textWidth($font, $size, $candidate) <= $maxWidth || $current === '') {
                $current = $candidate;

                continue;
            }

            $lines[] = $current;
            $current = $word;

            if (count($lines) === $maxLines - 1) {
                break;
            }
        }

        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }

        $joined = implode(' ', $lines);

        if (mb_strlen($joined) < mb_strlen(trim($text)) && $lines !== []) {
            $last = array_key_last($lines);

            while ($this->textWidth($font, $size, $lines[$last].'…') > $maxWidth && mb_strlen($lines[$last]) > 3) {
                $lines[$last] = mb_substr($lines[$last], 0, -1);
            }

            $lines[$last] = rtrim($lines[$last]).'…';
        }

        return $lines;
    }

    private function font(): string
    {
        $path = (string) config('aucvy.distribution.font', resource_path('fonts/Manrope-Variable.ttf'));

        return is_file($path) ? $path : resource_path('fonts/Manrope-Variable.ttf');
    }
}
