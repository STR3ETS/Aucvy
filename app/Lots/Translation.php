<?php

declare(strict_types=1);

namespace App\Lots;

use App\Enums\AiRunStatus;
use App\Ivy\StartTask;
use App\Ivy\Tasks\TranslateListing;
use App\Lots\Management\UpdateLot;
use App\Models\AiRun;
use App\Models\Lot;

/**
 * Vertalingen van de advertentie (briefing sectie 5, stap 4). De Nederlandse
 * tekst is de bron; een vertaling geldt alleen zolang de hash van die tekst
 * klopt. Ivy vertaalt bij publicatie en na een tekstwijziging.
 */
final class Translation
{
    /** @var list<string> Talen die we aanbieden; meer is een regel hier. */
    public const LOCALES = ['en'];

    public static function hash(Lot $lot): string
    {
        return hash('sha256', ($lot->title ?? '')."\n".($lot->description ?? ''));
    }

    public static function languageName(string $locale): string
    {
        return match ($locale) {
            'en' => 'Engels (Brits)',
            'de' => 'Duits',
            'fr' => 'Frans',
            default => $locale,
        };
    }

    public static function enabled(): bool
    {
        return (bool) config('aucvy.ai.translate') && (bool) config('aucvy.ai.enabled');
    }

    /**
     * Zet een vertaalrun klaar als die nodig is: alleen voor publieke
     * kavels, alleen als er nog geen geldige vertaling is (tenzij
     * geforceerd), en nooit twee keer dezelfde run tegelijk.
     */
    public static function start(Lot $lot, string $locale = 'en', bool $force = false): ?AiRun
    {
        if (! self::enabled() || ! in_array($lot->status, UpdateLot::EDITABLE, true)) {
            return null;
        }

        if (! $force && $lot->translation($locale) !== null) {
            return null;
        }

        $task = new TranslateListing($lot, $locale);

        $pending = AiRun::query()
            ->where('cache_key', $task->cacheKey())
            ->whereIn('status', [AiRunStatus::Queued, AiRunStatus::Running])
            ->exists();

        if ($pending && ! $force) {
            return null;
        }

        return StartTask::run($task, $lot->seller);
    }
}
