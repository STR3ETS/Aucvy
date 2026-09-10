<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\KycStatus;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Enums\ValueConfidence;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Zet de biedstorm klaar (hfst. 16): één kavel, één lopende veiling en een
 * leger geverifieerde bieders voor k6.
 *
 * Alleen buiten productie: loadtest-accounts op een productiedatabase zijn
 * een incident, geen test.
 */
final class PrepareLoadTest extends Command
{
    protected $signature = 'aucvy:loadtest-prepare {--bidders=500}';

    protected $description = 'Maakt de kavel, veiling en bieders voor de k6-biedstorm aan';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Niet op productie.');

            return self::FAILURE;
        }

        $bidders = max(1, (int) $this->option('bidders'));

        // Zelfde vrijstelling als een seeder: dit command ís een seeder met
        // een eigen naam, en de velden zijn bewust niet massa-toewijsbaar
        // voor de applicatie zelf.
        return Model::unguarded(fn (): int => $this->prepare($bidders));
    }

    private function prepare(int $bidders): int
    {
        $lot = Lot::firstOrCreate(
            ['slug' => 'loadtest-biedstorm'],
            [
                'public_id' => '99999',
                'seller_id' => User::factory()->verified()->create()->id,
                'category_id' => Category::query()->value('id'),
                'status' => LotStatus::Live,
                'sale_type' => SaleType::Auction,
                'title' => 'Loadtest - biedstorm',
                'description' => 'Kunstmatige kavel voor de 500-biedersstorm uit hfst. 16.',
                'quantity' => 1,
                'pickup_only' => true,
                // Ook deze kavel staat gewoon tussen de andere op de
                // veilingenpagina; zonder indicatie oogt hij kapot.
                'value_min_cents' => 10_000,
                'value_max_cents' => 25_000,
                'value_confidence' => ValueConfidence::Medium,
                'published_at' => now(),
            ],
        );

        $auction = Auction::firstOrCreate(
            ['lot_id' => $lot->id],
            [
                'start_price_cents' => 10_000,
                'current_price_cents' => 10_000,
                // Kleine stap: veel geldige verhogingen achter elkaar.
                'increment_table' => [['up_to_cents' => null, 'step_cents' => 100]],
                // Geen anti-snipe: de storm moet niet eindeloos verlengen.
                'anti_snipe_window_s' => 0,
                'anti_snipe_extend_s' => 0,
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addDays(2),
                'original_ends_at' => now()->addDays(2),
            ],
        );

        $this->withProgressBar(range(1, $bidders), function (int $i): void {
            User::firstOrCreate(
                ['email' => "storm{$i}@aucvy.test"],
                [
                    'name' => "Stormbieder {$i}",
                    'display_name' => null,
                    'password' => 'wachtwoord',
                    'email_verified_at' => now(),
                    'kyc_status' => KycStatus::Verified,
                    'kyc_verified_at' => now(),
                    'can_bid' => true,
                ],
            );
        });

        $this->newLine(2);
        $this->info("Klaar. Veiling-id: {$auction->id} - kavel: /kavel/{$lot->slug}");
        $this->line("Draai: k6 run tools/k6/bid-storm.js -e AUCTION_ID={$auction->id} -e BASE_URL=https://aucvy.test");

        return self::SUCCESS;
    }
}
