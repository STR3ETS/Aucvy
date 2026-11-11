<?php

declare(strict_types=1);

namespace App\Support\Charts;

/**
 * Een vloeiende lijn door meetpunten, als pad voor een svg.
 *
 * De kromme gaat door elk punt en schiet nergens door: tussen twee punten
 * blijft hij tussen hun waarden (monotone kubische interpolatie). Zo zakt een
 * lijn met bedragen nooit onder nul en komt hij nooit boven de piek uit.
 */
final class SmoothLine
{
    /**
     * @param  list<array{0: float|int, 1: float|int}>  $points  oplopend op x
     */
    public static function through(array $points): string
    {
        $count = count($points);

        if ($count === 0) {
            return '';
        }

        $path = sprintf('M%s,%s', self::number($points[0][0]), self::number($points[0][1]));

        if ($count === 1) {
            return $path;
        }

        $slopes = self::slopes($points);

        for ($index = 0; $index < $count - 1; $index++) {
            [$fromX, $fromY] = $points[$index];
            [$toX, $toY] = $points[$index + 1];
            $third = ($toX - $fromX) / 3;

            $path .= sprintf(
                ' C%s,%s %s,%s %s,%s',
                self::number($fromX + $third),
                self::number($fromY + $slopes[$index] * $third),
                self::number($toX - $third),
                self::number($toY - $slopes[$index + 1] * $third),
                self::number($toX),
                self::number($toY),
            );
        }

        return $path;
    }

    /**
     * De helling in elk punt, begrensd zodat de kromme niet doorschiet
     * (Fritsch en Carlson).
     *
     * @param  list<array{0: float|int, 1: float|int}>  $points
     * @return list<float>
     */
    private static function slopes(array $points): array
    {
        $count = count($points);
        $secants = [];

        for ($index = 0; $index < $count - 1; $index++) {
            $width = $points[$index + 1][0] - $points[$index][0];
            $secants[] = (float) $width === 0.0 ? 0.0 : ($points[$index + 1][1] - $points[$index][1]) / $width;
        }

        $slopes = [$secants[0]];

        for ($index = 1; $index < $count - 1; $index++) {
            // Een top of een dal blijft vlak; anders het gemiddelde van de twee kanten.
            $slopes[] = $secants[$index - 1] * $secants[$index] <= 0 ? 0.0 : ($secants[$index - 1] + $secants[$index]) / 2;
        }

        $slopes[] = $secants[$count - 2];

        foreach ($secants as $index => $secant) {
            if ((float) $secant === 0.0) {
                $slopes[$index] = 0.0;
                $slopes[$index + 1] = 0.0;

                continue;
            }

            $before = $slopes[$index] / $secant;
            $after = $slopes[$index + 1] / $secant;
            $length = $before ** 2 + $after ** 2;

            if ($length > 9) {
                $scale = 3 / sqrt($length);
                $slopes[$index] = $scale * $before * $secant;
                $slopes[$index + 1] = $scale * $after * $secant;
            }
        }

        return $slopes;
    }

    private static function number(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
