<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auctions\Actions\PlaceAutoBid;
use App\Auctions\Actions\PlaceBid;
use App\Auctions\Exceptions\BidRejected;
use App\Auctions\Support\BidGuards;
use App\Auctions\Support\IncrementTable;
use App\Auctions\Support\ViewerPresence;
use App\Enums\DepositStatus;
use App\Enums\KycStatus;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Models\Auction;
use App\Models\Deposit;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Laat het platform leven: een groepje gesimuleerde bieders biedt via de
 * échte biedmotor (borg, staffel, anti-snipe, events - alles geldt), en de
 * kijkersteller ademt organisch mee. Voor demo's op lokaal en staging.
 *
 *     php artisan aucvy:simulate            # blijft draaien, Ctrl+C stopt
 *     php artisan aucvy:simulate --once     # één beurt (voor tests)
 *
 * Draait de queue-worker mee, dan zie je alles realtime in de browser;
 * zonder worker haalt de pollingfallback de stand elke paar seconden op.
 */
final class SimulateActivity extends Command
{
    protected $signature = 'aucvy:simulate {--once : Eén beurt en stoppen} {--min=6 : Minimale pauze in seconden} {--max=18 : Maximale pauze in seconden}';

    protected $description = 'Simuleert bieders en kijkers op de lopende veilingen (niet voor productie)';

    /**
     * Kijkerstokens per veiling, zodat de teller stabiel ademt in plaats
     * van elke beurt te verspringen.
     *
     * @var array<int, array<int, string>>
     */
    private array $viewers = [];

    public function handle(PlaceBid $placeBid, PlaceAutoBid $placeAutoBid, BidGuards $guards, ViewerPresence $presence): int
    {
        if (app()->isProduction()) {
            $this->error('De simulator draait niet op productie.');

            return self::FAILURE;
        }

        $bots = $this->bots();
        $this->info('Simulator gestart met '.count($bots).' bieders. Ctrl+C stopt.');

        do {
            $this->tick($bots, $placeBid, $placeAutoBid, $guards, $presence);

            if ($this->option('once')) {
                break;
            }

            sleep(random_int((int) $this->option('min'), (int) $this->option('max')));
        } while (true);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, User>  $bots
     */
    private function tick(array $bots, PlaceBid $placeBid, PlaceAutoBid $placeAutoBid, BidGuards $guards, ViewerPresence $presence): void
    {
        $auctions = Auction::query()
            ->whereHas('lot', fn ($query) => $query->whereIn('status', [LotStatus::Live, LotStatus::Closing]))
            ->with('lot')
            ->get()
            ->filter(fn (Auction $auction): bool => ! $auction->isClosed());

        foreach ($auctions as $auction) {
            $this->breatheViewers($auction, $presence);
        }

        $candidates = $auctions
            ->filter(fn (Auction $auction): bool => $auction->lot->sale_type === SaleType::Auction)
            ->filter(fn (Auction $auction): bool => $auction->extension_count <= 5)
            ->filter(fn (Auction $auction): bool => $auction->current_price_cents < $this->priceCeiling($auction));

        if ($candidates->isEmpty()) {
            $this->line('Geen veiling waar nog gezond op te bieden valt.');

            return;
        }

        /** @var Auction $auction */
        $auction = $candidates->random();

        $bot = collect($bots)
            ->reject(fn (User $candidate): bool => $guards->isLeading($auction, $candidate))
            ->random();

        $this->ensureDeposit($auction, $bot);

        $table = IncrementTable::forAuction($auction);
        $minimum = $guards->minimumBid($auction);
        // Meestal het minimum, soms een stap of twee erboven - zoals mensen.
        $amount = $minimum + random_int(0, 2) * (random_int(1, 100) <= 30 ? $table->stepFor($minimum) : 0);

        try {
            $placeBid->handle(
                auction: $auction,
                user: $bot,
                amountCents: $amount,
                idempotencyKey: 'sim:'.Str::uuid()->toString(),
            );
        } catch (BidRejected $rejection) {
            $this->line("Bod geweigerd ({$rejection->reason->value}) - volgende beurt.");

            return;
        }

        $this->line(sprintf(
            '%s  %s bood %s op "%s"',
            now()->timezone(config('aucvy.display_timezone'))->format('H:i:s'),
            $bot->publicName(),
            Money::format($amount),
            Str::limit($auction->lot->title, 40),
        ));

        // Af en toe zet iemand een maximum: dan zie je proxygevechten ontstaan.
        if (random_int(1, 100) <= 15) {
            $maximum = $amount + random_int(3, 8) * $table->stepFor($amount);

            try {
                $placeAutoBid->handle($auction->fresh(), $bot, $maximum);
                $this->line("        {$bot->publicName()} stapt automatisch mee tot ".Money::format($maximum));
            } catch (BidRejected) {
                // Prima - een maximum is een extraatje, geen doel.
            }
        }
    }

    /**
     * De kijkersteller ademt: elke beurt komt er soms iemand bij of gaat er
     * iemand weg, binnen een band van twee tot negen kijkers.
     */
    private function breatheViewers(Auction $auction, ViewerPresence $presence): void
    {
        $tokens = $this->viewers[$auction->id] ?? [];

        $delta = random_int(-1, 1);
        $target = max(2, min(9, count($tokens) + $delta));

        while (count($tokens) < $target) {
            $tokens[] = 'sim-'.Str::random(12);
        }
        while (count($tokens) > $target) {
            array_pop($tokens);
        }

        foreach ($tokens as $token) {
            $presence->beat($auction->id, $token);
        }

        $this->viewers[$auction->id] = $tokens;
    }

    /**
     * Waar de simulator stopt met bieden: anderhalf keer de bovenkant van de
     * waarde-indicatie, of vijf keer de startprijs als die er niet is. Een
     * demo die eindeloos doorbiedt is geen demo maar ruis.
     */
    private function priceCeiling(Auction $auction): int
    {
        $lot = $auction->lot;

        return $lot->value_max_cents !== null
            ? (int) round($lot->value_max_cents * 1.5)
            : $auction->start_price_cents * 5;
    }

    /**
     * @return array<int, User>
     */
    private function bots(): array
    {
        $names = [
            'Jeroen de Boer', 'Sanne Visser', 'Pieter Bakker', 'Lotte van Dijk',
            'Thomas Mulder', 'Emma Smit', 'Daan Meijer', 'Fleur de Groot',
        ];

        // Zelfde vrijstelling als een seeder: dit command ís demodata.
        return Model::unguarded(fn (): array => collect($names)
            ->map(fn (string $name, int $index): User => User::firstOrCreate(
                ['email' => 'sim'.($index + 1).'@aucvy.test'],
                [
                    'name' => $name,
                    'display_name' => null,
                    'password' => Str::random(32),
                    'email_verified_at' => now(),
                    'kyc_status' => KycStatus::Verified,
                    'kyc_verified_at' => now(),
                    'can_bid' => true,
                ],
            ))->all());
    }

    /**
     * Zelfde route als de DemoSeeder: de borggate blijft gewoon werken, we
     * simuleren alleen de betaalprovider.
     */
    private function ensureDeposit(Auction $auction, User $bot): void
    {
        Deposit::updateOrCreate(
            ['user_id' => $bot->id, 'auction_id' => $auction->id],
            [
                'amount_cents' => 25_000,
                'provider' => 'demo',
                'provider_id' => 'sim_'.Str::random(10),
                'status' => DepositStatus::Authorised,
            ],
        );
    }
}
