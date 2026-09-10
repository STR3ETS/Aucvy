<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auctions\Actions\PlaceBid;
use App\Auctions\Exceptions\BidRejected;
use App\Enums\BidStatus;
use App\Models\Auction;
use App\Models\AuctionEvent;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Laat een groep bieders op exact hetzelfde moment hetzelfde bedrag bieden.
 *
 * Dit is de valkuil uit hoofdstuk 6, letterlijk: "twee biedingen van € 9.000
 * komen tegelijk binnen, lezen allebei 'huidig € 8.900' en winnen allebei."
 *
 * De Pest-tests bewijzen de logica, maar draaien in één proces en kunnen dus
 * niet aantonen dat de rij-lock werkt. Deze opdracht start echte parallelle
 * processen.
 *
 * ---------------------------------------------------------------------------
 * Waarom een startbarrière
 * ---------------------------------------------------------------------------
 *
 * Zonder synchronisatie is dit geen test. Een PHP-proces starten en Laravel
 * booten kost ruim 200 ms, en die tijd verschilt per proces - het eerste bod is
 * dan allang gecommit voordat het tweede ook maar bij de database is. Elke
 * worker wacht daarom tot een gezamenlijk startmoment, zodat ze werkelijk op
 * dezelfde milliseconde toeslaan.
 *
 * ---------------------------------------------------------------------------
 * Waarom de uitkomst van elke worker geteld wordt
 * ---------------------------------------------------------------------------
 *
 * Alleen tellen dat er één bod doorkwam is te zwak: ook zónder rij-lock komt er
 * één doorheen, want de unieke index op (auction_id, sequence) vangt de botsing
 * af. Maar dan valt de verliezer om met een databasefout in plaats van een
 * nette weigering - en dat is precies het verschil tussen "afgehandeld" en
 * "toevallig goed afgelopen". Daarom is een enkele onverwachte fout hier
 * genoeg om de test te laten falen.
 *
 * Dit vervangt niet de loadtest uit hoofdstuk 16; die hoort met k6 of Artillery
 * over HTTP te gaan, vóór de launch.
 */
final class StressTestBidding extends Command
{
    protected $signature = 'auctions:stress-test
        {--bidders=20 : Aantal bieders dat tegelijk hetzelfde bedrag biedt}
        {--worker : Interne vlag: voert één enkel bod uit}
        {--auction= : Alleen met --worker}
        {--user= : Alleen met --worker}
        {--amount= : Alleen met --worker}
        {--start-at= : Alleen met --worker: unix-tijd met microseconden}';

    protected $description = 'Laat bieders op dezelfde milliseconde bieden en controleert of er precies één wint';

    public function handle(): int
    {
        if ($this->option('worker')) {
            return $this->placeSingleBid();
        }

        if (app()->isProduction()) {
            $this->error('Deze opdracht maakt testdata aan en draait niet op productie.');

            return self::FAILURE;
        }

        return $this->runStressTest();
    }

    private function runStressTest(): int
    {
        $bidderCount = max(2, (int) $this->option('bidders'));

        $this->components->info("Opzetten: één kavel, {$bidderCount} bieders.");

        [$auction, $bidders, $amount] = $this->prepare($bidderCount);

        // Ruim genomen: alle processen moeten Laravel gebooteerd hebben vóór
        // dit moment, anders komen ze alsnog na elkaar binnen.
        $startAt = microtime(true) + 1.5 + ($bidderCount * 0.05);

        $this->components->info('Alle bieders bieden tegelijk '.$this->euro($amount).'.');

        $results = Process::concurrently(function ($pool) use ($auction, $bidders, $amount, $startAt): void {
            foreach ($bidders as $bidder) {
                $pool->command([
                    PHP_BINARY,
                    'artisan',
                    'auctions:stress-test',
                    '--worker',
                    '--auction='.$auction->id,
                    '--user='.$bidder->id,
                    '--amount='.$amount,
                    '--start-at='.$startAt,
                ])->path(base_path());
            }
        });

        return $this->report($auction, $bidderCount, $this->tally($results));
    }

    /**
     * @return array{0: Auction, 1: Collection<int, User>, 2: int}
     */
    private function prepare(int $bidderCount): array
    {
        $lot = Lot::factory()->live()->create([
            'title' => 'Stresstest '.now()->format('H:i:s'),
        ]);

        $auction = Auction::factory()->create([
            'lot_id' => $lot->id,
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
            // Geen verlenging: die zou de uitkomst vertroebelen.
            'ends_at' => now()->addHour(),
            'original_ends_at' => now()->addHour(),
        ]);

        $bidders = User::factory()->verified()->count($bidderCount)->create();

        return [$auction, $bidders, 100_000];
    }

    /**
     * @return array{accepted: int, rejected: array<string, int>, errors: array<int, string>}
     */
    private function tally(mixed $results): array
    {
        $accepted = 0;
        $rejected = [];
        $errors = [];

        foreach ($results as $result) {
            $output = trim($result->output());

            if (str_starts_with($output, 'geaccepteerd')) {
                $accepted++;

                continue;
            }

            if (str_starts_with($output, 'geweigerd:')) {
                $reason = trim(Str::after($output, 'geweigerd:'));
                $rejected[$reason] = ($rejected[$reason] ?? 0) + 1;

                continue;
            }

            $errors[] = $output !== '' ? $output : trim($result->errorOutput());
        }

        return ['accepted' => $accepted, 'rejected' => $rejected, 'errors' => $errors];
    }

    /**
     * @param  array{accepted: int, rejected: array<string, int>, errors: array<int, string>}  $tally
     */
    private function report(Auction $auction, int $bidderCount, array $tally): int
    {
        $auction->refresh();

        $stored = Bid::where('auction_id', $auction->id)->count();
        $winning = Bid::where('auction_id', $auction->id)->where('status', BidStatus::Winning)->count();
        $sequences = Bid::where('auction_id', $auction->id)->pluck('sequence');
        $eventSequences = AuctionEvent::where('auction_id', $auction->id)->pluck('sequence');

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Bieders tegelijk</>', (string) $bidderCount);
        $this->components->twoColumnDetail('<fg=gray>Bod geaccepteerd</>', (string) $tally['accepted']);

        foreach ($tally['rejected'] as $reason => $count) {
            $this->components->twoColumnDetail("<fg=gray>Netjes geweigerd ({$reason})</>", (string) $count);
        }

        $this->components->twoColumnDetail('<fg=gray>Onverwachte fouten</>', (string) count($tally['errors']));
        $this->components->twoColumnDetail('<fg=gray>Eindstand</>', $this->euro($auction->current_price_cents));
        $this->newLine();

        $problems = [];

        // Precies één winnaar. Hier gaat een ontbrekende rij-lock op stuk.
        if ($winning !== 1) {
            $problems[] = "Er zijn {$winning} winnende biedingen in plaats van 1.";
        }

        // Iedereen bood hetzelfde bedrag, dus er kan er maar één door.
        if ($stored !== 1) {
            $problems[] = "Er staan {$stored} biedingen in de database; dat hoort er 1 te zijn.";
        }

        /*
         | De kern van deze test. Zonder rij-lock komt er óók één bod door - de
         | unieke index vangt de rest af - maar dan struikelen de verliezers
         | over een databasefout in plaats van netjes geweigerd te worden.
         */
        if ($tally['errors'] !== []) {
            $problems[] = count($tally['errors']).' bieder(s) kregen een onverwachte fout in plaats van een nette weigering.';

            foreach (array_slice($tally['errors'], 0, 3) as $error) {
                $problems[] = '  → '.Str::limit($error, 160);
            }
        }

        $expectedRejections = $bidderCount - $tally['accepted'] - count($tally['errors']);

        if (array_sum($tally['rejected']) !== $expectedRejections) {
            $problems[] = 'Niet elke verliezende bieder kreeg een reden te horen.';
        }

        if ($sequences->count() !== $sequences->unique()->count()) {
            $problems[] = 'Twee biedingen delen hetzelfde volgnummer.';
        }

        if ($eventSequences->count() !== $eventSequences->unique()->count()) {
            $problems[] = 'Twee events delen hetzelfde volgnummer.';
        }

        if ($auction->bid_count !== $stored) {
            $problems[] = "De teller bid_count staat op {$auction->bid_count}, maar er zijn {$stored} biedingen.";
        }

        if ($problems === []) {
            $this->components->info('Geslaagd: één winnaar, alle anderen netjes geweigerd, geen dubbele volgnummers.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        return self::FAILURE;
    }

    /**
     * Eén bod, uitgevoerd in een eigen proces.
     */
    private function placeSingleBid(): int
    {
        $auction = Auction::findOrFail((int) $this->option('auction'));
        $user = User::findOrFail((int) $this->option('user'));

        $this->waitForStartSignal();

        try {
            app(PlaceBid::class)->handle(
                auction: $auction,
                user: $user,
                amountCents: (int) $this->option('amount'),
                idempotencyKey: (string) Str::uuid(),
            );

            $this->line('geaccepteerd');
        } catch (BidRejected $rejected) {
            // Verwacht: iedereen behalve de winnaar hoort hier te landen.
            $this->line('geweigerd: '.$rejected->reason->value);
        } catch (Throwable $exception) {
            // Niet verwacht: een deadlock, een dubbele sleutel of iets anders
            // wat de gebruiker nooit te zien mag krijgen. Juist dit moet de
            // stresstest zichtbaar maken.
            $this->line(class_basename($exception).': '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Wacht tot het gezamenlijke startmoment, zodat alle processen werkelijk
     * op dezelfde milliseconde toeslaan.
     */
    private function waitForStartSignal(): void
    {
        $startAt = (float) $this->option('start-at');

        if ($startAt <= 0.0) {
            return;
        }

        // Grof wachten tot vlak ervoor, daarna kort bezig blijven: usleep is
        // op deze schaal niet nauwkeurig genoeg.
        $coarse = $startAt - microtime(true) - 0.05;

        if ($coarse > 0) {
            usleep((int) ($coarse * 1_000_000));
        }

        while (microtime(true) < $startAt) {
            // Bewust leeg.
        }
    }

    private function euro(int $cents): string
    {
        return '€ '.number_format($cents / 100, 2, ',', '.');
    }
}
