<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WatchlistItem;
use App\Notifications\WatchlistEnding;
use Illuminate\Console\Command;

/**
 * Wie een kavel volgt, hoort het als de veiling bijna sluit (briefing
 * sectie 3). Eén keer per gevolgde kavel, binnen het laatste uur.
 */
final class NotifyWatchlistEnding extends Command
{
    protected $signature = 'watchlist:notify-ending {--within=60 : Minuten voor het einde}';

    protected $description = 'Meldt volgers dat een gevolgde veiling binnenkort sluit';

    public function handle(): int
    {
        $horizon = now()->addMinutes((int) $this->option('within'));
        $sent = 0;

        WatchlistItem::query()
            ->whereNull('notified_ending_at')
            ->whereHas('lot.auction', fn ($auction) => $auction
                ->whereNull('closed_at')
                ->whereNull('paused_at')
                ->where('ends_at', '>', now())
                ->where('ends_at', '<=', $horizon))
            ->with(['user', 'lot.auction'])
            ->chunkById(200, function ($items) use (&$sent): void {
                foreach ($items as $item) {
                    $item->notified_ending_at = now();
                    $item->save();

                    if ($item->user === null) {
                        continue;
                    }

                    $item->user->notify(new WatchlistEnding($item->lot));
                    $sent++;
                }
            });

        $this->info("{$sent} volgers gewaarschuwd.");

        return self::SUCCESS;
    }
}
