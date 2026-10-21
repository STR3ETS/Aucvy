<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Lots\LotSearch;
use App\Models\SavedSearch;
use App\Notifications\AlertMatches;
use Illuminate\Console\Command;

/**
 * Bewaarde zoekopdrachten langs de nieuwe kavels leggen (briefing sectie 3:
 * alerts; sectie 8: de alert-treffer). Elke actieve zoekopdracht krijgt de
 * kavels die sinds de vorige ronde zijn gepubliceerd en erbij passen. Geen
 * treffer, geen bericht.
 */
final class MatchSavedSearches extends Command
{
    protected $signature = 'alerts:match {--limit=10 : Maximaal aantal kavels per bericht}';

    protected $description = 'Stuurt alert-berichten voor nieuwe kavels die aan een bewaarde zoekopdracht voldoen';

    public function handle(LotSearch $search): int
    {
        $sent = 0;

        SavedSearch::query()
            ->active()
            ->with('user')
            ->chunkById(100, function ($searches) use ($search, &$sent): void {
                foreach ($searches as $saved) {
                    $since = $saved->last_run_at ?? $saved->created_at;
                    // Een halve minuut speling: een publicatie die net aan het
                    // committen is, valt anders tussen twee runs door.
                    $until = now()->subSeconds(30);
                    $limit = (int) $this->option('limit');
                    $lots = $search->newSince($saved->filters(), $since, $limit, $until);

                    // Meer dan past in één bericht: de rest komt de volgende keer.
                    $saved->last_run_at = $lots->count() >= $limit ? $lots->last()->published_at : $until;
                    $saved->save();

                    if ($lots->isEmpty() || $saved->user === null) {
                        continue;
                    }

                    $saved->user->notify(new AlertMatches($saved, $lots));
                    $sent++;
                }
            });

        $this->info("{$sent} alert-berichten verstuurd.");

        return self::SUCCESS;
    }
}
