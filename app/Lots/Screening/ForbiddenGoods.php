<?php

declare(strict_types=1);

namespace App\Lots\Screening;

use App\Models\Lot;
use Illuminate\Support\Str;

/**
 * De conceptlijst verboden waar (config/listings.php) toegepast op een
 * kavel: welke groepen raken de titel, het merk of de omschrijving.
 *
 * Een treffer is een signaal voor een medewerker, nooit een automatische
 * afwijzing: "jachtgeweerkast" bevat "geweer" en is toch gewoon een kast.
 */
final class ForbiddenGoods
{
    /**
     * @return list<string> De labels van de geraakte groepen.
     */
    public function hits(Lot $lot): array
    {
        $text = $this->normalise(implode(' ', array_filter([
            $lot->title,
            $lot->brand,
            $lot->model,
            $lot->description,
        ])));

        $hits = [];

        foreach ((array) config('listings.forbidden_goods', []) as $group) {
            foreach ((array) ($group['keywords'] ?? []) as $keyword) {
                $word = $this->normalise((string) $keyword);

                // Vanaf vijf letters ook als begin van een samenstelling; korte
                // woorden ("nep", "pup") alleen heel, anders raakt "nep" ook "Nepal".
                $tail = strlen($word) >= 5 ? '' : '(?![a-z])';

                if ($word !== '' && preg_match('/(?<![a-z])'.preg_quote($word, '/').$tail.'/', $text) === 1) {
                    $hits[] = (string) $group['label'];

                    continue 2;
                }
            }
        }

        return $hits;
    }

    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return array_values(array_map(
            fn (array $group): string => (string) $group['label'],
            (array) config('listings.forbidden_goods', []),
        ));
    }

    private function normalise(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }
}
