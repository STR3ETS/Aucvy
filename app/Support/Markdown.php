<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Vrije tekst van een gebruiker in een Markdown-mail.
 *
 * De notificatiemails worden als Markdown gerenderd. Een bezichtigingsnotitie
 * of een orderbericht met `[Bevestig hier](https://nep.example)` wordt dan een
 * klikbare link naast de echte knoppen. Daarom krijgt alles wat een gebruiker
 * zelf getypt heeft (kaveltitels, namen, berichten) hier een backslash voor
 * elk teken dat in Markdown iets betekent; de tekst oogt daarna precies zoals
 * hij getypt is. HTML hoeft hier niet ontsnapt: dat doet het Blade-sjabloon.
 *
 * Alleen voor regels die als Markdown gerenderd worden (`line`, `greeting`).
 * Een onderwerpregel is platte tekst en krijgt dit niet.
 */
final class Markdown
{
    public static function escape(string $text): string
    {
        // Koppelingen, nadruk, code, koppen, tabellen en doorhalen.
        $escaped = preg_replace('/([\\\\`*_\[\]#|~])/u', '\\\\$1', $text) ?? $text;

        // Lijsten en genummerde lijsten ontstaan alleen aan het begin van een
        // regel; daar het lijstteken ontsnappen.
        $escaped = preg_replace('/^(\s*)([-+])(?=\s)/mu', '$1\\\\$2', $escaped) ?? $escaped;

        return preg_replace('/^(\s*\d+)\.(?=\s)/mu', '$1\\\\.', $escaped) ?? $escaped;
    }

    /**
     * Een vervangingsarray voor `__()`: alle tekstwaarden ontsnapt, behalve
     * URL's - die staan in de vertaling al in een koppeling en moeten heel
     * blijven.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function escapeValues(array $values): array
    {
        foreach ($values as $key => $value) {
            if (! is_string($value) || $key === 'url' || str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                continue;
            }

            $values[$key] = self::escape($value);
        }

        return $values;
    }
}
