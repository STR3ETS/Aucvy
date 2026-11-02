<?php

declare(strict_types=1);

namespace App\Reviews;

use Illuminate\Support\Str;

/**
 * De automatische controle vóór publicatie (besluit Raph, 2 november 2026:
 * "het Google-principe, automatische moderatie op scheldwoorden, politieke
 * kleuring, discriminatie").
 *
 * Bewust een woordenlijst en een paar patronen, geen model: de uitkomst
 * moet uitlegbaar en reproduceerbaar zijn, en een verkoper mag een review
 * niet weg kunnen krijgen door een taalmodel te sturen. Wat de lijst
 * tegenhoudt, wordt niet gepubliceerd maar gaat naar beheer; een mens
 * beslist. De lijsten staan in config/reviews.php zodat beheer ze kan
 * aanvullen zonder codewijziging.
 */
final class ReviewModerator
{
    /**
     * De reden om tegen te houden, of null als de tekst door mag.
     */
    public function reasonToHold(?string $text): ?ReviewReason
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $normalised = $this->normalise($text);

        // Discriminatie eerst: dat is de zwaarste reden en wint als een
        // tekst op meerdere lijsten staat.
        $checks = [
            [ReviewReason::Discrimination, (array) config('reviews.moderation.discrimination', [])],
            [ReviewReason::Offensive, (array) config('reviews.moderation.offensive', [])],
            [ReviewReason::Political, (array) config('reviews.moderation.political', [])],
        ];

        foreach ($checks as [$reason, $words]) {
            foreach ($words as $word) {
                // Partijnamen alleen als heel woord: "sp" mag "spullen" niet raken.
                $prefix = $reason !== ReviewReason::Political;

                if ($this->containsWord($normalised, $this->normalise((string) $word), $prefix)) {
                    return $reason;
                }
            }
        }

        // Persoonsgegevens: een telefoonnummer of e-mailadres hoort niet in
        // een publieke review (AVG), van wie het ook is.
        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]+/u', $text) === 1
            || preg_match('/(?:\+31|0031|\b0)[\s-]?6(?:[\s-]?\d){8}\b/u', $text) === 1) {
            return ReviewReason::PersonalData;
        }

        return null;
    }

    /**
     * Kleine letters, accenten weg, en de gebruikelijke vermommingen
     * (cijfers voor letters, sterretjes, herhaalde letters) gelijkgetrokken.
     */
    private function normalise(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = strtr($text, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's', '*' => '']);

        return (string) preg_replace('/(.)\1{2,}/', '$1$1', $text);
    }

    /**
     * Een woord als heel woord, of vanaf vijf letters ook als begin van een
     * samenstelling ("kankerverkoper"); nooit midden in een onschuldig
     * woord, en korte woorden ("kut", "lul") alleen als heel woord.
     */
    private function containsWord(string $haystack, string $word, bool $prefix = true): bool
    {
        if ($word === '') {
            return false;
        }

        $tail = $prefix && strlen($word) >= 5 ? '' : '(?![a-z])';

        return preg_match('/(?<![a-z])'.preg_quote($word, '/').$tail.'/', $haystack) === 1;
    }
}
