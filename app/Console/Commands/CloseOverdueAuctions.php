<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auctions\Actions\CloseAuction;
use App\Models\Auction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Het vangnet onder CloseAuctionJob.
 *
 * Draait elke minuut en zoekt veilingen die over hun eindtijd heen zijn maar
 * nog niet gesloten - door een herstart, een verloren job of een worker die
 * omviel.
 *
 * Deze opdracht hoort niets te vinden. Vindt hij structureel wél iets, dan is
 * dat een incident en geen routine, en daarom logt hij als waarschuwing in
 * plaats van als mededeling (hfst. 6).
 */
final class CloseOverdueAuctions extends Command
{
    protected $signature = 'auctions:close-overdue';

    protected $description = 'Sluit veilingen die over hun eindtijd heen zijn blijven hangen';

    public function handle(CloseAuction $closeAuction): int
    {
        $overdue = Auction::overdue()->get();

        if ($overdue->isEmpty()) {
            $this->info('Geen achterstallige veilingen.');

            return self::SUCCESS;
        }

        Log::warning('Achterstallige veilingen gevonden door het vangnet.', [
            'count' => $overdue->count(),
            'auction_ids' => $overdue->pluck('id')->all(),
        ]);

        $failed = 0;

        foreach ($overdue as $auction) {
            try {
                $closeAuction->handle($auction, onlyIfDue: true);
                $this->warn("Veiling {$auction->id} alsnog gesloten.");
            } catch (Throwable $exception) {
                $failed++;

                // Eén veiling die niet dicht wil mag de rest niet blokkeren.
                Log::error('Achterstallige veiling kon niet gesloten worden.', [
                    'auction_id' => $auction->id,
                    'exception' => $exception->getMessage(),
                ]);

                $this->error("Veiling {$auction->id} kon niet gesloten worden: {$exception->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
