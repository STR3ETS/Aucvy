<?php

declare(strict_types=1);

namespace App\Lots\Scan;

/**
 * Een streepjescode is pas een productcode als het controlegetal klopt
 * (GTIN-8, -12, -13 en -14, allemaal modulo 10).
 */
final class Gtin
{
    public static function normalise(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (! in_array(strlen($digits), [8, 12, 13, 14], true)) {
            return null;
        }

        return self::isValid($digits) ? $digits : null;
    }

    public static function isValid(string $digits): bool
    {
        if (! ctype_digit($digits) || ! in_array(strlen($digits), [8, 12, 13, 14], true)) {
            return false;
        }

        $sum = 0;
        $length = strlen($digits);

        for ($i = 0; $i < $length - 1; $i++) {
            $digit = (int) $digits[$length - 2 - $i];
            $sum += $i % 2 === 0 ? $digit * 3 : $digit;
        }

        return (10 - ($sum % 10)) % 10 === (int) $digits[$length - 1];
    }
}
