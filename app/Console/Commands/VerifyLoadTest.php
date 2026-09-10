<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BidStatus;
use App\Models\Auction;
use App\Models\AuctionEvent;
use App\Models\Bid;
use App\Support\Money\Money;
use Illuminate\Console\Command;

/**
 * De controle ná de storm: niet hoe snel het was, maar of het klópt.
 *
 * De vier invarianten van de biedmotor (hfst. 6), geverifieerd op de data
 * die de storm achterliet. Faalt er één, dan is de loadtest gezakt - hoe
 * mooi de latencies ook waren.
 */
final class VerifyLoadTest extends Command
{
    protected $signature = 'aucvy:loadtest-verify {auction}';

    protected $description = 'Controleert de biedmotor-invarianten na de k6-biedstorm';

    public function handle(): int
    {
        $auction = Auction::query()->findOrFail((int) $this->argument('auction'));

        $bids = Bid::query()->where('auction_id', $auction->id)->get();
        $accepted = $bids->whereIn('status', [BidStatus::Winning, BidStatus::Outbid]);

        $checks = [
            'elk geaccepteerd bod heeft een uniek, gaatloos volgnummerbereik' => function () use ($auction): bool {
                $sequences = AuctionEvent::query()
                    ->where('auction_id', $auction->id)
                    ->orderBy('sequence')
                    ->pluck('sequence');

                return $sequences->count() === $sequences->unique()->count()
                    && ($sequences->isEmpty() || $sequences->last() === $auction->last_sequence);
            },
            'er is precies één winnend bod' => fn (): bool => $bids->where('status', BidStatus::Winning)->count() === 1,
            'de prijs is het hoogste geaccepteerde bod' => fn (): bool => $accepted->isEmpty()
                || $auction->current_price_cents === $accepted->max('amount_cents'),
            'de teller klopt met de werkelijkheid' => fn (): bool => $auction->bid_count === $accepted->count(),
            'geen enkel bod is dubbel verwerkt (idempotency)' => fn (): bool => $bids->pluck('idempotency_key')->count()
                === $bids->pluck('idempotency_key')->unique()->count(),
        ];

        $failed = 0;

        foreach ($checks as $label => $check) {
            $ok = $check();
            $this->line(($ok ? '<info>✓</info>' : '<error>✗</error>').' '.$label);
            $failed += $ok ? 0 : 1;
        }

        $this->newLine();
        $this->line("Biedingen totaal: {$bids->count()} - geaccepteerd: {$accepted->count()} - prijs: ".Money::format($auction->current_price_cents));

        if ($failed > 0) {
            $this->error("{$failed} invariant(en) geschonden - de storm heeft iets kapotgemaakt.");

            return self::FAILURE;
        }

        $this->info('Alle invarianten overeind. De motor hield stand.');

        return self::SUCCESS;
    }
}
