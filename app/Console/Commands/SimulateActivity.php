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
use App\Enums\LotCondition;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Enums\ValueConfidence;
use App\Enums\VatRegime;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Deposit;
use App\Models\Lot;
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
    protected $signature = 'aucvy:simulate {--once : Eén beurt en stoppen} {--min=6 : Minimale pauze in seconden} {--max=18 : Maximale pauze in seconden} {--publish : Plaats in deze beurt gegarandeerd een nieuwe kavel}';

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
        // Zo nu en dan verschijnt er een verse kavel - veiling, afslag of
        // koop direct, verspreid over de categorieën.
        if ($this->option('publish') || random_int(1, 100) <= 10) {
            $this->publishLot($bots);
        }

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
     * Plaatst een nieuwe kavel uit de catalogus: realistische titel, waarde-
     * indicatie met jitter, en een willekeurige verkoopvorm met passende
     * instellingen. Geen foto's - de eerlijke plaatshouder uit het ontwerp
     * doet dan zijn werk.
     *
     * @param  array<int, User>  $bots
     */
    private function publishLot(array $bots): void
    {
        $item = $this->catalogue()[array_rand($this->catalogue())];

        $category = Category::query()->where('slug', $item['category'])->first();

        if ($category === null) {
            return;
        }

        // Jitter van -15% tot +15% zodat dezelfde titel nooit dezelfde
        // cijfers heeft.
        $jitter = fn (int $cents): int => (int) round($cents * random_int(85, 115) / 100 / 500) * 500;
        $valueMin = $jitter($item['value_min_cents']);
        $valueMax = max($valueMin + 50_00, $jitter($item['value_max_cents']));
        $saleType = [SaleType::Auction, SaleType::Auction, SaleType::Dutch, SaleType::BuyNow][random_int(0, 3)];
        $seller = $bots[array_rand($bots)];

        [$lot, $label] = Model::unguarded(function () use ($item, $category, $seller, $saleType, $valueMin, $valueMax): array {
            $suffix = random_int(1000, 9999);

            do {
                $publicId = (string) random_int(20_000, 89_999);
            } while (Lot::query()->where('public_id', $publicId)->exists());

            $lot = Lot::create([
                'public_id' => $publicId,
                'seller_id' => $seller->id,
                'category_id' => $category->id,
                'title' => $item['title'],
                'slug' => Str::slug($item['title']).'-'.$suffix,
                'description' => $item['description'],
                'condition' => [LotCondition::New, LotCondition::LikeNew, LotCondition::Good, LotCondition::Used][random_int(0, 3)],
                'status' => LotStatus::Live,
                'sale_type' => $saleType,
                'vat_regime' => random_int(0, 1) === 1 ? VatRegime::Vat21 : VatRegime::Margin,
                'value_min_cents' => $valueMin,
                'value_max_cents' => $valueMax,
                'value_confidence' => [ValueConfidence::Medium, ValueConfidence::High, ValueConfidence::Medium][random_int(0, 2)],
                'transport_from_cents' => random_int(0, 1) === 1 ? random_int(6, 40) * 1_000 : null,
                'pickup_only' => false,
                'viewing_available' => random_int(0, 1) === 1,
                'quantity' => 1,
                'completeness_score' => random_int(55, 92),
                'published_at' => now(),
            ]);

            $endsAt = now()->addHours(random_int(20, 140));

            $auction = [
                'lot_id' => $lot->id,
                'starts_at' => now(),
                'ends_at' => $endsAt,
                'original_ends_at' => $endsAt,
                'increment_table' => config('aucvy.auction.increment_table'),
                'anti_snipe_window_s' => config('aucvy.auction.anti_snipe_window_seconds'),
                'anti_snipe_extend_s' => config('aucvy.auction.anti_snipe_extend_seconds'),
            ];

            $label = match ($saleType) {
                SaleType::Auction => 'veiling',
                SaleType::Dutch => 'afslag',
                SaleType::BuyNow => 'koop direct',
            };

            match ($saleType) {
                // Start onder de indicatie: dan valt er wat te winnen.
                SaleType::Auction => Auction::create([
                    ...$auction,
                    'start_price_cents' => (int) round($valueMin * 0.45 / 500) * 500,
                    'current_price_cents' => (int) round($valueMin * 0.45 / 500) * 500,
                ]),
                // Start boven de indicatie en zakt erdoorheen.
                SaleType::Dutch => Auction::create([
                    ...$auction,
                    'start_price_cents' => (int) round($valueMax * 1.25 / 500) * 500,
                    'current_price_cents' => (int) round($valueMax * 1.25 / 500) * 500,
                    'dutch_start_cents' => (int) round($valueMax * 1.25 / 500) * 500,
                    'dutch_floor_cents' => (int) round($valueMin * 0.5 / 500) * 500,
                    'dutch_step_cents' => max(2_500, (int) round($valueMax / 80 / 500) * 500),
                    'dutch_interval_s' => [20, 25, 30][random_int(0, 2)],
                ]),
                SaleType::BuyNow => Auction::create([
                    ...$auction,
                    'start_price_cents' => (int) round($valueMin * 0.6 / 500) * 500,
                    'current_price_cents' => (int) round($valueMin * 0.6 / 500) * 500,
                    'buy_now_price_cents' => (int) round(($valueMin + $valueMax) / 2 / 500) * 500,
                    'buy_now_active' => true,
                ]),
            };

            return [$lot, $label];
        });

        $this->line(sprintf(
            '%s  Nieuw geplaatst (%s): "%s" door %s',
            now()->timezone(config('aucvy.display_timezone'))->format('H:i:s'),
            $label,
            Str::limit($lot->title, 45),
            $seller->publicName(),
        ));
    }

    /**
     * Realistische kavels om te plaatsen, verspreid over de categorieën.
     *
     * @return array<int, array{title: string, description: string, category: string, value_min_cents: int, value_max_cents: int}>
     */
    private function catalogue(): array
    {
        return [
            ['title' => 'Elektrische heftruck Jungheinrich EFG 216', 'description' => 'Hefvermogen 1.600 kg, triplex mast, accu vervangen in 2024. Direct inzetbaar uit een draaiend magazijn.', 'category' => 'machines-techniek-heftrucks', 'value_min_cents' => 850_000, 'value_max_cents' => 1_150_000],
            ['title' => 'Afkortzaag met toevoerbaan, Metabo KGS 315', 'description' => 'Uit een timmerwerkplaats. Zaagblad recent vervangen, toevoerbaan van drie meter inbegrepen.', 'category' => 'machines-techniek-houtbewerking', 'value_min_cents' => 45_000, 'value_max_cents' => 85_000],
            ['title' => 'Schroefcompressor Atlas Copco GA11', 'description' => '11 kW, 8 bar, 21.000 draaiuren. Onderhoudshistorie aanwezig, laatste beurt februari.', 'category' => 'machines-techniek-compressoren-pompen', 'value_min_cents' => 320_000, 'value_max_cents' => 480_000],
            ['title' => 'Horeca-koelwerkbank RVS, drie deuren', 'description' => 'Gastro-norm, werkt op 230V. Lichte gebruikssporen op het werkblad, koeltechniek in orde.', 'category' => 'horeca-keuken-koeling-vriescellen', 'value_min_cents' => 65_000, 'value_max_cents' => 110_000],
            ['title' => 'Professioneel zesspits gasfornuis met oven', 'description' => 'Uit een restaurantkeuken, inclusief afdekplaten. Aansluitklaar voor aardgas.', 'category' => 'horeca-keuken-kookapparatuur', 'value_min_cents' => 90_000, 'value_max_cents' => 160_000],
            ['title' => 'Partij van 24 horecastoelen, eiken met leer', 'description' => 'Uniforme set uit een brasserie. Twee stoelen met lichte beschadiging aan de poot, eerlijk vermeld.', 'category' => 'horeca-keuken-horecameubilair', 'value_min_cents' => 55_000, 'value_max_cents' => 95_000],
            ['title' => 'Partij van 16 Herman Miller Aeron bureaustoelen', 'description' => 'Maat B, volledig verstelbaar. Uit een kantoorontruiming, allemaal gecontroleerd op de mechanieken.', 'category' => 'kantoor-inventaris-bureaustoelen', 'value_min_cents' => 380_000, 'value_max_cents' => 560_000],
            ['title' => 'Complete vergaderinventaris: tafel plus 12 stoelen', 'description' => 'Massief eiken vergadertafel van vier meter met bijpassende stoelen en een dressoir.', 'category' => 'kantoor-inventaris-complete-inventaris', 'value_min_cents' => 120_000, 'value_max_cents' => 210_000],
            ['title' => 'Partij zit-sta bureaus, elektrisch verstelbaar (8 stuks)', 'description' => 'Merk Linak-onderstel, blad 160x80. Alle motoren getest.', 'category' => 'kantoor-inventaris-bureaus-kasten', 'value_min_cents' => 140_000, 'value_max_cents' => 240_000],
            ['title' => 'Gesloten aanhanger, dubbelasser 2.600 kg', 'description' => 'Afmeting 300x150x180. Nieuwe banden, kentekenbewijs aanwezig, disselslot inbegrepen.', 'category' => 'voertuigen-transport-aanhangers-opleggers', 'value_min_cents' => 210_000, 'value_max_cents' => 320_000],
            ['title' => 'Rolsteiger 8 meter werkhoogte, compleet', 'description' => 'Inclusief stabilisatoren, kantplanken en twee platforms. Keuringssticker tot volgend jaar.', 'category' => 'bouw-materieel-steigers', 'value_min_cents' => 85_000, 'value_max_cents' => 140_000],
            ['title' => 'Trilplaat Wacker Neuson DPU 4545', 'description' => 'Diesel, 45 kN. Vers onderhoud, start direct. Uit een beëindigd grondverzetbedrijf.', 'category' => 'bouw-materieel-grondverzet', 'value_min_cents' => 180_000, 'value_max_cents' => 290_000],
            ['title' => 'Restpartij werkkleding, 240 stuks, nieuw in verpakking', 'description' => 'Mix van maten M t/m XXL, bekende merken. Doorverkoop toegestaan.', 'category' => 'winkel-voorraad-restpartijen', 'value_min_cents' => 95_000, 'value_max_cents' => 170_000],
            ['title' => 'Winkelinrichting: toonbank plus 12 meter wandstelling', 'description' => 'Licht eiken met stalen frames. Demontage door koper, alles gelabeld.', 'category' => 'winkel-voorraad-displays-inrichting', 'value_min_cents' => 75_000, 'value_max_cents' => 130_000],
            ['title' => 'Consoleboot 4,7 meter met 50pk viertakt', 'description' => 'Inclusief trailer. Motor uit 2021, 90 draaiuren, antifouling vorig seizoen.', 'category' => 'boten-recreatie-sloepen', 'value_min_cents' => 950_000, 'value_max_cents' => 1_400_000],
            ['title' => 'Sony FX3 cinema-camera met twee lenzen', 'description' => 'Inclusief 24-70 f/2.8 en 85 f/1.8, drie accu\'s en koffer. Sluiterteller laag.', 'category' => 'elektronica-cameras-optiek', 'value_min_cents' => 380_000, 'value_max_cents' => 520_000],
            ['title' => 'Partij van 10 Dell Latitude laptops, 14 inch', 'description' => 'i7, 16GB, 512GB, allemaal gewist en voorzien van verse installatie. Lichte gebruikssporen.', 'category' => 'elektronica-computers', 'value_min_cents' => 240_000, 'value_max_cents' => 380_000],
        ];
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
