<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use App\Models\Lot;
use Illuminate\Support\Str;

/**
 * OutputValidator voor de chat (Ivy-briefing hfst. 13): een eurobedrag dat
 * niet uit een toolresultaat komt, een verboden woord, een kavel of link
 * die niet publiek is, "ik heb geboden" zonder uitgevoerd voorstel, of
 * contactgegevens van een ander. Wat niet door de toets komt, wordt
 * opnieuw gevraagd en daarna vervangen door een vaste tekst.
 */
final class ChatValidator
{
    public const FORBIDDEN = ['escrow', 'derdengelden', 'gegarandeerd', 'nog maar', 'laatste kans', 'nu of nooit'];

    public const string FALLBACK = 'Dat kan ik zo niet zeggen. Kijk op de kavelpagina voor de actuele stand, of vraag een medewerker.';

    /**
     * @param  list<string>  $allowedAmounts  Bedragen die in toolresultaten voorkwamen (als cijferreeks).
     * @return list<string> De gevonden problemen; leeg is goed.
     */
    public function problems(string $text, array $allowedAmounts, bool $executedProposal, bool $hasReserveFacts = false): array
    {
        $problems = [];
        $lower = Str::lower($text);

        foreach (self::FORBIDDEN as $phrase) {
            if (str_contains($lower, $phrase)) {
                $problems[] = 'forbidden:'.$phrase;
            }
        }

        if (preg_match_all('/€\s?(\d{1,3}(?:\.\d{3})*(?:,\d{2})?|\d+(?:,\d{2})?)/u', $text, $matches) > 0) {
            foreach ($matches[1] as $amount) {
                $digits = preg_replace('/\D/', '', $amount) ?? '';

                if ($digits !== '' && ! in_array($digits, $allowedAmounts, true) && ! in_array(rtrim($digits, '0'), array_map(fn ($a) => rtrim($a, '0'), $allowedAmounts), true)) {
                    $problems[] = 'amount:'.$amount;
                }
            }
        }

        if (preg_match('/\bik heb (?:geboden|een bod geplaatst|gekocht|gepubliceerd)\b/u', $lower) === 1 && ! $executedProposal) {
            $problems[] = 'claimed_action';
        }

        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.]+|\b(?:\+31|06)[\s-]?\d{1,2}[\s-]?\d{6,8}\b/u', $text) === 1) {
            $problems[] = 'contact_details';
        }

        if (preg_match_all('/\/kavel\/([a-z0-9-]+)/i', $text, $links) > 0) {
            foreach ($links[1] as $slug) {
                if (! Lot::query()->where('slug', $slug)->whereIn('status', ['live', 'closing', 'scheduled', 'sold'])->exists()) {
                    $problems[] = 'link:'.$slug;
                }
            }
        }

        return $problems;
    }
}
