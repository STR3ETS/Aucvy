<?php

declare(strict_types=1);

namespace App\Support\Health;

use App\Models\ErrorEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Het foutlog van beheer (besluit Raph, 2 november 2026). Laravel meldt
 * elke fout hier naast het gewone log; dezelfde soort fout telt op in één
 * rij in plaats van duizend regels.
 *
 * Wat hier staat is zichtbaar voor medewerkers, dus geheimen gaan eruit:
 * API-sleutels, tokens en e-mailadressen worden gemaskeerd. Een fout bij
 * het vastleggen mag de oorspronkelijke fout nooit verdringen.
 */
final class ErrorLog
{
    private const MESSAGE_LIMIT = 1000;

    public function record(Throwable $exception): void
    {
        try {
            $file = $this->relativePath($exception->getFile());
            $fingerprint = hash('sha256', $exception::class.'|'.$file.'|'.$exception->getLine());
            $request = app()->runningInConsole() ? null : request();
            $now = now();

            DB::transaction(function () use ($exception, $file, $fingerprint, $request, $now): void {
                $event = ErrorEvent::query()->where('fingerprint', $fingerprint)->lockForUpdate()->first();

                $context = [
                    'message' => $this->scrub($exception->getMessage()),
                    'url' => $request === null ? null : Str::limit($request->fullUrl(), 490, ''),
                    'method' => $request?->method(),
                    'last_user_id' => $request?->user()?->getKey(),
                    'last_seen_at' => $now,
                ];

                if ($event === null) {
                    ErrorEvent::create([
                        ...$context,
                        'fingerprint' => $fingerprint,
                        'class' => $exception::class,
                        'file' => $file,
                        'line' => $exception->getLine(),
                        'occurrences' => 1,
                        'first_seen_at' => $now,
                    ]);

                    return;
                }

                // Komt een afgehandelde fout terug, dan gaat hij weer open.
                $event->fill([
                    ...$context,
                    'occurrences' => $event->occurrences + 1,
                    'resolved_at' => null,
                ])->save();
            });
        } catch (Throwable) {
            // Bewust stil: het gewone log heeft de fout al.
        }
    }

    public function scrub(string $message): string
    {
        $patterns = [
            '/sk-[A-Za-z0-9_\-]{6,}/' => 'sk-***',
            '/(Bearer\s+)[A-Za-z0-9._\-]+/i' => '$1***',
            '/(key|token|secret|password)=([^&\s]+)/i' => '$1=***',
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/' => '***@***',
        ];

        return Str::limit((string) preg_replace(array_keys($patterns), array_values($patterns), $message), self::MESSAGE_LIMIT);
    }

    private function relativePath(string $file): string
    {
        return Str::after(str_replace('\\', '/', $file), str_replace('\\', '/', base_path()).'/');
    }
}
