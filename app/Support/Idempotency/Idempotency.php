<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Dezelfde aanvraag twee keer geeft hetzelfde antwoord (briefing sectie 2
 * en 10): publiceren, betalen en delen krijgen een sleutel van de client;
 * de uitkomst van de eerste keer wordt bewaard en bij een herhaling
 * teruggegeven zonder het werk opnieuw te doen.
 *
 * De sleutel is per bereik (`scope`) uniek, zodat een hergebruikte sleutel
 * van een ander object niets kan teruggeven dat er niet bij hoort.
 */
final class Idempotency
{
    public const HEADER = 'Idempotency-Key';

    /**
     * @param  Closure(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    public static function once(string $scope, string $key, Closure $callback, int $ttlHours = 24): array
    {
        $stored = IdempotencyKey::query()
            ->where('scope', $scope)
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->first();

        if ($stored !== null) {
            return $stored->response;
        }

        $response = $callback();

        try {
            IdempotencyKey::create([
                'scope' => $scope,
                'key' => $key,
                'response' => $response,
                'expires_at' => now()->addHours($ttlHours),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Twee gelijktijdige aanvragen: de eerste rij wint, het antwoord
            // is hetzelfde werk, dus teruggeven wat er al staat.
            return IdempotencyKey::query()
                ->where('scope', $scope)
                ->where('key', $key)
                ->sole()
                ->response;
        }

        return $response;
    }
}
