<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Leest de `Idempotency-Key`-header van een verzoek dat geld kost.
 *
 * Verplicht, en met opzet geen stille terugval op een willekeurige waarde:
 * dan zou een client die de header vergeet zonder waarschuwing dubbel kunnen
 * bieden of dubbel kunnen kopen (hfst. 7).
 */
trait HasIdempotencyKey
{
    public function idempotencyKey(): string
    {
        $key = $this->header('Idempotency-Key');

        if (! is_string($key) || trim($key) === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => __('auctions.errors.idempotency_key_required'),
            ]);
        }

        // De kolom is 64 tekens; langer afkappen is beter dan een databasefout
        // op het biedpad.
        return mb_substr(trim($key), 0, 64);
    }
}
