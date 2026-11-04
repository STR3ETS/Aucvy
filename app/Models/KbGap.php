<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Vragen die Ivy niet kon beantwoorden, geanonimiseerd en geteld, zodat
 * de kennisbank groeit waar het nodig is.
 */
#[Fillable(['question_hash', 'sample_question', 'count', 'role', 'first_seen_at', 'last_seen_at', 'resolved_article_id'])]
class KbGap extends Model
{
    public $timestamps = false;

    public static function record(string $question, string $role): void
    {
        $normalised = Str::lower(trim(preg_replace('/\s+/', ' ', $question) ?? $question));
        // Geen persoonsgegevens: e-mailadressen en nummers eruit.
        $normalised = (string) preg_replace('/[\w.+-]+@[\w-]+\.[\w.]+|\+?\d[\d\s-]{6,}\d/', '[weggelaten]', $normalised);
        $hash = hash('sha256', $normalised);

        $gap = self::query()->where('question_hash', $hash)->first();

        if ($gap !== null) {
            $gap->forceFill(['count' => $gap->count + 1, 'last_seen_at' => now()])->save();

            return;
        }

        self::create(['question_hash' => $hash, 'sample_question' => Str::limit($normalised, 300, ''), 'count' => 1, 'role' => $role, 'first_seen_at' => now(), 'last_seen_at' => now()]);
    }
}
