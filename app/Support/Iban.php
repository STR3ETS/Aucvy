<?php

declare(strict_types=1);

namespace App\Support;

/**
 * IBAN-controle zonder bibliotheek: opschonen, lengte en de mod-97-check.
 * Genoeg om tikfouten te vangen; of de rekening bestaat weet alleen de
 * betaalprovider.
 */
final class Iban
{
    public static function normalise(string $input): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));
    }

    public static function isValid(string $input): bool
    {
        $iban = self::normalise($input);

        if (! preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $numeric = '';

        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        // Mod 97 in stukken, zodat het ook zonder bcmath klopt.
        $remainder = 0;

        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) (($remainder.$chunk) % 97);
        }

        return $remainder === 1;
    }

    public static function last4(string $input): string
    {
        return substr(self::normalise($input), -4);
    }
}
