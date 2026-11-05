<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Auctions\Actions\PlaceBid;
use App\Auctions\Support\BidGuards;
use App\Enums\AiRunStatus;
use App\Enums\BidStatus;
use App\Enums\CompanyRole;
use App\Enums\DisputeStatus;
use App\Enums\DocumentKind;
use App\Enums\InsightSeverity;
use App\Enums\KycStatus;
use App\Enums\LotCondition;
use App\Enums\LotStatus;
use App\Enums\NotificationChannel;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Enums\SaleType;
use App\Enums\UserType;
use App\Enums\ValueConfidence;
use App\Enums\VatRegime;
use App\Enums\ViewingStatus;
use App\Lots\Interest\Interest;
use App\Models\AiInsight;
use App\Models\AiRun;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Dispute;
use App\Models\Location;
use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\LotSignal;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Review;
use App\Models\Sale;
use App\Models\SavedSearch;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Viewing;
use App\Models\WatchlistItem;
use App\Notifications\AlertMatches;
use App\Notifications\OrderStatusChanged;
use App\Notifications\OutbidPush;
use App\Notifications\PayoutReleased;
use App\Notifications\VerificationDecided;
use App\Notifications\ViewingRequested;
use App\Notifications\WatchlistEnding;
use App\Orders\Invoicing\Invoices;
use App\Support\Money\Money;
use App\Support\PlatformFacts;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Throwable;

/**
 * Een vol demoaccount voor Anna Bakker (anna@aucvy.test): zowel koper als
 * verkoper, zodat elke pagina en elke grafiek iets te laten zien heeft.
 *
 * Als verkoper (Bakker Interieur B.V.): live kavels met biedingen van de
 * laatste 24 uur, interesse per kavel met herkomst, een jaar afgeronde
 * verkopen met uitbetalingen en reviews, een gebundelde veiling en een
 * concept. Als koper: lopende biedingen, volglijst, bestellingen in elke
 * staat, een geschil, facturen, zoekalerts, meldingen en gesprekken.
 *
 * Idempotent: opnieuw draaien vult aan en ververst de tijden (sluittijden
 * en de biedingen per uur), maar maakt niets dubbel. Er gaan geen mails en
 * geen queue-jobs uit en er worden geen AI-calls gedaan.
 *
 * Alleen voor lokaal en staging - draait niet mee met DatabaseSeeder.
 *
 *     php artisan db:seed --class=AnnaDemoSeeder
 */
final class AnnaDemoSeeder extends Seeder
{
    private User $anna;

    private Company $company;

    private User $otherSeller;

    /** @var list<User> */
    private array $bidders = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('AnnaDemoSeeder draait niet op productie.');

            return;
        }

        $anna = User::query()->where('email', 'anna@aucvy.test')->first();
        $otherSeller = User::query()->where('email', 'verkoper@aucvy.test')->first();

        if ($anna === null || $otherSeller === null) {
            $this->command?->error('Draai eerst de DemoSeeder: anna@aucvy.test of verkoper@aucvy.test ontbreekt.');

            return;
        }

        // Geen mail, geen jobs, geen AI: dit is alleen data om naar te kijken.
        Queue::fake();
        Mail::fake();
        config(['aucvy.pricing.deposit_tiers' => [], 'aucvy.ai.enabled' => false]);

        $this->anna = $anna;
        $this->otherSeller = $otherSeller;
        $this->company = $this->company();
        $this->bidders = $this->bidders();

        $liveLots = $this->sellerLots();
        $this->bidsOnOwnLots($liveLots);
        $this->interest($liveLots);
        $this->sellerExtras($liveLots);
        $this->salesHistory();
        $this->extraSales();

        $this->buyerAuctions();
        $this->buyerOrders();
        $this->inbox($liveLots);

        // Foto's bij elke kavel zonder media.
        $this->call(DemoPhotoSeeder::class);

        $this->command?->newLine();
        $this->command?->info('Het account van Anna Bakker is gevuld. Inloggen: anna@aucvy.test / wachtwoord.');
        $this->command?->info('Als verkoper: /dashboard. Als koper: /dashboard?rol=koper.');
    }

    /**
     * Anna wordt eigenaar van een geverifieerd bedrijf: alleen een bedrijf
     * mag verkopen, en zo kloppen de verkoperskaart en de facturen.
     */
    private function company(): Company
    {
        $company = Company::firstOrCreate(
            ['slug' => 'bakker-interieur'],
            [
                'name' => 'Bakker Interieur B.V.',
                'kvk_number' => '61234567',
                'vat_number' => 'NL861234567B01',
                'description' => 'Projectinrichter uit Amersfoort. We verkopen showroommodellen, retouren en complete inventarissen uit opgeleverde projecten.',
            ],
        );

        $company->forceFill([
            'verified_at' => $company->verified_at ?? now()->subMonths(13),
            'ubo_verified_at' => $company->ubo_verified_at ?? now()->subMonths(13),
            // Bewust geen uitbetaalrekening: met een rekening vraagt het inloggen
            // om een code per mail (hfst. 15), en dit account moet in één stap open.
            'payout_iban_last4' => null,
            'payout_iban_name' => null,
        ])->save();

        $this->anna->forceFill([
            'company_id' => $company->id,
            'company_role' => CompanyRole::Owner,
            'type' => UserType::Business,
            'display_name' => $this->anna->display_name ?: 'Bakker Interieur',
            'phone' => $this->anna->phone ?: '+31612345678',
        ])->save();

        return $company;
    }

    /**
     * Zes bieders: de twee uit de DemoSeeder en vier erbij, twee zakelijk.
     *
     * @return list<User>
     */
    private function bidders(): array
    {
        $make = fn (string $email, string $name, bool $business): User => User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'display_name' => null,
                'password' => 'wachtwoord',
                'email_verified_at' => now(),
                'type' => $business ? UserType::Business : UserType::Private,
                'kyc_status' => KycStatus::Verified,
                'kyc_verified_at' => now()->subMonths(2),
                'can_bid' => true,
            ],
        );

        return [
            $make('bram@aucvy.test', 'Bram Klaassen', false),
            $make('carla@aucvy.test', 'Carla de Vries', false),
            $make('daan@aucvy.test', 'Daan Hoekstra', true),
            $make('eva@aucvy.test', 'Eva Smits', false),
            $make('femke@aucvy.test', 'Femke Jansen', true),
            $make('gijs@aucvy.test', 'Gijs van Dam', false),
        ];
    }

    /**
     * De kavels van Anna: vijf veilingen, een afslag, koop direct, een
     * geplande kavel, een onverkochte en een concept.
     *
     * @return array<string, Lot>
     */
    private function sellerLots(): array
    {
        $lots = [];

        $definitions = [
            ['7701', 'Espressomachine La Marzocco Linea PB, 2-groeps', 'Uit een koffiebar die verbouwt. In 2022 gekocht, jaarlijks onderhouden, ontkalkt en getest. Inclusief twee portafilters en de onderhoudshistorie.', 'horeca-keuken-kookapparatuur', 250_000, 340_000, ValueConfidence::High, 120_000, 30, true, 96],
            ['7702', 'Partij 24 design-barkrukken, eiken met leer', 'Vierentwintig barkrukken uit een hotellobby. Eiken frame, cognac leren zitting. Enkele met lichte gebruikssporen aan de voetsteun, zichtbaar op de foto\'s.', 'horeca-keuken-horecameubilair', 90_000, 130_000, ValueConfidence::Medium, 40_000, 72, false, 88],
            ['7703', 'Zit-sta bureaus elektrisch, 12 stuks', 'Twaalf elektrisch verstelbare bureaus (160 x 80 cm) uit een kantoorverhuizing. Allemaal getest; twee bladen hebben een kras.', 'kantoor-inventaris-bureaus-kasten', 150_000, 210_000, ValueConfidence::High, 60_000, 5, false, 91],
            ['7706', 'Combisteamer Rational iCombi Pro 6-1/1', 'Elektrische combisteamer uit 2021, zes niveaus. Dagelijks gebruikt in een lunchzaak, ontkalkt en gereinigd. Onderstel en gastronormbakken inbegrepen.', 'horeca-keuken-kookapparatuur', 420_000, 560_000, ValueConfidence::Medium, 180_000, 48, true, 93],
            ['7710', 'Lounge-set showroommodel, 3-zits en twee fauteuils', 'Showroommodel in bouclé, nooit bij een klant gestaan. Kleine vlek op de linker armleuning, zie foto 4.', 'kantoor-inventaris-complete-inventaris', 110_000, 160_000, ValueConfidence::Low, 45_000, 96, false, 79],
        ];

        foreach ($definitions as [$publicId, $title, $description, $category, $min, $max, $confidence, $start, $hours, $hot, $score]) {
            $lot = $this->lot($this->anna, $this->company, [
                'public_id' => $publicId,
                'title' => $title,
                'description' => $description,
                'category' => $category,
                'condition' => LotCondition::Good,
                'vat_regime' => VatRegime::Vat21,
                'value_min_cents' => $min,
                'value_max_cents' => $max,
                'value_confidence' => $confidence,
                'completeness_score' => $score,
                'viewing_available' => true,
                'pickup_only' => false,
                'transport_from_cents' => 8_500,
                'is_hot' => $hot,
                'hot_since' => $hot ? now()->subHours(9) : null,
            ]);
            $this->openAuction($lot, ['start_price_cents' => $start, 'reserve_price_cents' => (int) round($min * 0.8)], $hours);
            $lots[$publicId] = $lot;
        }

        // Afslag: de prijs zakt elke twee minuten.
        $dutch = $this->lot($this->anna, $this->company, [
            'public_id' => '7704',
            'title' => 'Vergadertafel massief eiken, 4 meter, met 10 stoelen',
            'description' => 'Massief eiken tafel van vier meter met tien bijpassende stoelen. Het blad is opnieuw geolied. Alleen ophalen, de tafel gaat in twee delen.',
            'category' => 'kantoor-inventaris-bureaus-kasten',
            'sale_type' => SaleType::Dutch,
            'condition' => LotCondition::Good,
            'vat_regime' => VatRegime::Vat21,
            'value_min_cents' => 220_000,
            'value_max_cents' => 310_000,
            'value_confidence' => ValueConfidence::Medium,
            'completeness_score' => 84,
        ]);
        $this->openAuction($dutch, [
            'start_price_cents' => 480_000,
            'current_price_cents' => 480_000,
            'dutch_start_cents' => 480_000,
            'dutch_floor_cents' => 150_000,
            'dutch_step_cents' => 2_500,
            'dutch_interval_s' => 120,
            'starts_at' => now()->subMinutes(4),
        ], 8, restart: true);
        $lots['7704'] = $dutch;

        // Koop direct, met een vaste prijs.
        $buyNow = $this->lot($this->anna, $this->company, [
            'public_id' => '7705',
            'title' => 'Koelvitrine Gram, 2 glazen deuren',
            'description' => 'Staande koelvitrine met twee glazen deuren en ledverlichting. Koelt tot 2 graden. Vorig jaar nieuwe deurrubbers.',
            'category' => 'horeca-keuken-koeling-vriescellen',
            'sale_type' => SaleType::BuyNow,
            'condition' => LotCondition::Used,
            'vat_regime' => VatRegime::Margin,
            'value_min_cents' => 70_000,
            'value_max_cents' => 105_000,
            'value_confidence' => ValueConfidence::Medium,
            'completeness_score' => 82,
        ]);
        $this->openAuction($buyNow, ['start_price_cents' => 89_000, 'buy_now_price_cents' => 89_000, 'buy_now_active' => true], 120);
        $lots['7705'] = $buyNow;

        // Gepland, onverkocht en een concept: de overige staten in "Mijn kavels".
        $this->lot($this->anna, $this->company, [
            'public_id' => '7707',
            'title' => 'Palletstelling 40 strekkende meter, 3 niveaus',
            'description' => 'Complete palletstelling uit ons oude magazijn. Gedemonteerd en gebundeld, klaar voor transport.',
            'category' => 'machines-techniek',
            'status' => LotStatus::Scheduled,
            'condition' => LotCondition::Used,
            'completeness_score' => 76,
            'published_at' => null,
        ]);
        $this->lot($this->anna, $this->company, [
            'public_id' => '7709',
            'title' => 'Receptiebalie wit hoogglans, 3 meter',
            'description' => 'Receptiebalie uit een opgeleverd project. De reserveprijs is niet gehaald.',
            'category' => 'kantoor-inventaris-bureaus-kasten',
            'status' => LotStatus::Unsold,
            'condition' => LotCondition::Good,
            'completeness_score' => 81,
            'published_at' => now()->subDays(20),
        ]);
        $this->lot($this->anna, $this->company, [
            'public_id' => '7708',
            'title' => 'Designfauteuil',
            'description' => 'Fauteuil uit de showroom.',
            'category' => 'kantoor-inventaris-complete-inventaris',
            'status' => LotStatus::Draft,
            'condition' => null,
            'completeness_score' => 46,
            'published_at' => null,
        ]);

        return $lots;
    }

    /**
     * Biedingen op Anna's veilingen, daarna uitgesmeerd over de laatste 24
     * uur met een avondpiek: zo is de grafiek "Biedingen per uur" gevuld.
     *
     * @param  array<string, Lot>  $lots
     */
    private function bidsOnOwnLots(array $lots): void
    {
        $targets = ['7701' => 22, '7702' => 9, '7703' => 16, '7706' => 14, '7710' => 5];
        $guards = app(BidGuards::class);

        foreach ($targets as $publicId => $target) {
            $auction = $lots[$publicId]->auction()->first();

            if ($auction === null) {
                continue;
            }

            $placed = (int) $auction->bid_count;

            while ($placed < $target) {
                $auction = $auction->fresh();
                $bidder = $this->bidders[($placed + (int) $publicId) % count($this->bidders)];
                $amount = $guards->minimumBid($auction);

                try {
                    app(PlaceBid::class)->handle(auction: $auction, user: $bidder, amountCents: $amount, idempotencyKey: 'anna-demo:'.$auction->id.':'.$placed);
                } catch (Throwable $exception) {
                    $this->command?->warn('Bod overgeslagen op '.$publicId.': '.$exception->getMessage());

                    break;
                }

                $placed++;
            }
        }

        // De tijden: per uur van de dag een gewicht, de avond het drukst.
        $auctionIds = Auction::query()->whereIn('lot_id', collect($lots)->pluck('id'))->pluck('id');
        $bids = Bid::query()->whereIn('auction_id', $auctionIds)->orderBy('id')->get(['id', 'auction_id']);

        if ($bids->isEmpty()) {
            return;
        }

        $timezone = (string) config('aucvy.display_timezone');
        $now = CarbonImmutable::now($timezone);
        $weights = [0 => 1, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 1, 6 => 2, 7 => 4, 8 => 6, 9 => 5, 10 => 7, 11 => 5, 12 => 6, 13 => 5, 14 => 4, 15 => 5, 16 => 7, 17 => 8, 18 => 11, 19 => 12, 20 => 10, 21 => 8, 22 => 5, 23 => 3];
        $moments = [];
        mt_srand(7701);

        for ($hoursAgo = 23; $hoursAgo >= 0; $hoursAgo--) {
            $hour = $now->subHours($hoursAgo);
            $count = (int) round($weights[(int) $hour->format('G')] / array_sum($weights) * $bids->count());

            for ($index = 0; $index < $count; $index++) {
                $moment = $hour->startOfHour()->addMinutes(mt_rand(0, 59))->addSeconds(mt_rand(0, 59));
                $moments[] = $moment->greaterThan($now) ? $now->subMinutes(mt_rand(1, 20)) : $moment;
            }
        }

        while (count($moments) < $bids->count()) {
            $moments[] = $now->subMinutes(mt_rand(5, 1380));
        }

        usort($moments, fn (CarbonImmutable $a, CarbonImmutable $b): int => $a->getTimestamp() <=> $b->getTimestamp());
        $moments = array_slice($moments, 0, $bids->count());

        // Per veiling in volgorde, maar alle veilingen door elkaar over de dag.
        $ordered = $bids->groupBy('auction_id')
            ->flatMap(fn ($group) => $group->values()->map(fn (Bid $bid, int $index): array => ['id' => $bid->id, 'key' => ($index + 0.5) / $group->count()]))
            ->sortBy('key')
            ->values();

        foreach ($ordered as $index => $row) {
            DB::table('bids')->where('id', $row['id'])->update(['created_at' => $moments[$index]->utc()->format('Y-m-d H:i:s')]);
        }
    }

    /**
     * Gemeten interesse: kijkers, weergaven, opgeslagen, gedeeld en
     * geopende documenten, met herkomst. Eén keer per kavel.
     *
     * @param  array<string, Lot>  $lots
     */
    private function interest(array $lots): void
    {
        $volumes = [
            '7701' => [1_120, 86, 31, 26],
            '7702' => [412, 38, 12, 9],
            '7703' => [704, 52, 18, 21],
            '7704' => [291, 33, 13, 6],
            '7705' => [187, 21, 6, 4],
            '7706' => [868, 64, 22, 19],
            '7710' => [143, 11, 4, 2],
        ];

        foreach ($volumes as $publicId => [$viewers, $saved, $shared, $documents]) {
            $this->signals($lots[$publicId], $viewers, $saved, $shared, $documents);
        }
    }

    private function signals(Lot $lot, int $viewers, int $saved, int $shared, int $documents): void
    {
        if (! LotSignal::query()->where('lot_id', $lot->id)->where('visitor_hash', 'like', 'demo-%')->exists()) {
            $sources = ['alert' => 34, 'instagram' => 24, 'google' => 20, 'shared' => 11, 'direct' => 7, 'facebook' => 3, 'linkedin' => 1];
            $pool = [];

            foreach ($sources as $source => $weight) {
                $pool = array_merge($pool, array_fill(0, $weight, $source));
            }

            mt_srand($lot->id);
            $rows = [];
            $row = fn (string $kind, int $index, int $minutesAgo, ?string $bucket = null): array => [
                'lot_id' => $lot->id,
                'kind' => $kind,
                'visitor_hash' => 'demo-'.$lot->id.'-'.$index,
                'user_id' => null,
                'source' => $kind === LotSignal::VIEWER ? $pool[mt_rand(0, count($pool) - 1)] : null,
                'bucket' => $bucket ?? now()->subMinutes($minutesAgo)->format('Y-m-d'),
                'occurred_at' => now()->subMinutes($minutesAgo),
            ];

            // Zes op de tien kijkers kwamen de laatste twee dagen: dat telt mee voor de score.
            for ($index = 0; $index < $viewers; $index++) {
                $minutesAgo = $index % 10 < 6 ? mt_rand(5, 2_800) : mt_rand(2_900, 9_900);
                $rows[] = $row(LotSignal::VIEWER, $index, $minutesAgo);
                $rows[] = $row(LotSignal::VIEW, $index, $minutesAgo, 'v-'.$index.'-a');

                if ($index % 2 === 0) {
                    $rows[] = $row(LotSignal::VIEW, $index, max(1, $minutesAgo - 30), 'v-'.$index.'-b');
                }
            }

            foreach ([LotSignal::SAVED => $saved, LotSignal::SHARED => $shared, LotSignal::DOCUMENT => $documents] as $kind => $count) {
                for ($index = 0; $index < $count; $index++) {
                    $rows[] = $row($kind, $index, mt_rand(5, 2_800));
                }
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                LotSignal::query()->insert($chunk);
            }
        }

        $lot->forceFill(['interest_score' => Interest::score($lot)])->save();
    }

    /**
     * Documenten, bezichtigingen, volgers, de gebundelde veiling en wat het
     * AI-team deed: alles wat de verkoperspagina's verder vult.
     *
     * @param  array<string, Lot>  $lots
     */
    private function sellerExtras(array $lots): void
    {
        foreach ($lots as $publicId => $lot) {
            if ($lot->documents()->count() === 0) {
                LotDocument::factory()->checkedByIvy()->create(['lot_id' => $lot->id, 'kind' => DocumentKind::Invoice, 'original_filename' => 'aankoopfactuur-'.$publicId.'.pdf']);
                LotDocument::factory()->checkedByIvy()->create(['lot_id' => $lot->id, 'kind' => DocumentKind::Manual, 'original_filename' => 'handleiding-'.$publicId.'.pdf']);

                if (in_array($publicId, ['7701', '7706'], true)) {
                    LotDocument::factory()->create(['lot_id' => $lot->id, 'kind' => DocumentKind::Maintenance, 'original_filename' => 'onderhoud-'.$publicId.'.pdf']);
                }
            }

            foreach (array_slice($this->bidders, 0, $publicId === '7701' ? 5 : 2) as $watcher) {
                WatchlistItem::firstOrCreate(['user_id' => $watcher->id, 'lot_id' => $lot->id]);
            }

            foreach (['recognise_lot' => 'ivy', 'draft_listing' => 'nova', 'estimate_value' => 'atlas'] as $task => $agent) {
                AiRun::firstOrCreate(
                    ['cache_key' => substr(hash('sha256', 'anna-demo:'.$task.':'.$lot->id), 0, 64)],
                    [
                        'subject_type' => Lot::class,
                        'subject_id' => $lot->id,
                        'user_id' => $this->anna->id,
                        'agent' => $agent,
                        'task' => $task,
                        'model' => 'demo',
                        'input' => ['lot' => $lot->public_id],
                        'output' => ['demo' => true],
                        'confidence' => 0.86,
                        'tokens_in' => 0,
                        'tokens_out' => 0,
                        'cost_cents' => 0,
                        'latency_ms' => 900,
                        'status' => AiRunStatus::Completed,
                    ],
                );
            }
        }

        // Eigendomsbewijs bij elke live kavel van Anna, op twee na: zo blijft
        // "Wacht op jou" gevarieerd in plaats van acht keer dezelfde taak.
        Lot::query()
            ->where('seller_id', $this->anna->id)
            ->where('status', LotStatus::Live)
            ->whereNotIn('public_id', ['7705', '7710'])
            ->whereDoesntHave('documents', fn ($query) => $query->where('kind', DocumentKind::Ownership->value))
            ->get()
            ->each(fn (Lot $lot) => LotDocument::factory()->checkedByIvy()->create([
                'lot_id' => $lot->id,
                'kind' => DocumentKind::Ownership,
                'visibility' => 'private',
                'original_filename' => 'eigendomsbewijs-'.$lot->public_id.'.pdf',
            ]));

        // Bezichtigingen: twee aanvragen en een bevestigde.
        foreach ([['7701', 0, 2, ViewingStatus::Requested], ['7701', 2, 3, ViewingStatus::Confirmed], ['7706', 4, 2, ViewingStatus::Requested], ['7703', 1, 1, ViewingStatus::Confirmed]] as [$publicId, $bidder, $days, $status]) {
            Viewing::firstOrCreate(
                ['lot_id' => $lots[$publicId]->id, 'user_id' => $this->bidders[$bidder]->id],
                ['slot_at' => now()->addDays($days)->setTime(14, 0), 'status' => $status, 'note' => 'Ik kom met een bus, is er een laadperron?'],
            );
        }

        // Een gebundelde veiling met twee van de kavels (Auction Studio).
        $sale = Sale::updateOrCreate(['slug' => 'opheffing-showroom-bakker-interieur-7790'], [
            'public_id' => '7790',
            'seller_id' => $this->anna->id,
            'company_id' => $this->company->id,
            'title' => 'Opheffing showroom Bakker Interieur',
            'description' => "De showroom in Amersfoort verhuist. Alle modellen en de horeca-apparatuur uit de koffiebar gaan in één veiling, per kavel te koop.\nAlles is gebruikt maar goed onderhouden; kom kijken op de bezichtigingsdag.",
            'ends_at' => now()->addDays(3)->setTime(20, 0),
            'closing_gap_seconds' => 120,
            'viewing_at' => now()->addDays(2)->setTime(14, 0),
            'viewing_note' => 'Op afspraak; het adres volgt per mail na bevestiging.',
            'pickup_from' => now()->addDays(4)->toDateString(),
            'pickup_until' => now()->addDays(8)->toDateString(),
            'pickup_note' => 'Zelf laden; een palletwagen en een laadklep zijn aanwezig.',
            'terms' => "Betaling binnen 48 uur na toewijzing.\nOphalen alleen in de ophaalperiode, op afspraak.",
            'published_at' => now()->subDays(2),
        ]);

        foreach (['7702', '7706'] as $position => $publicId) {
            $lots[$publicId]->forceFill(['sale_id' => $sale->id, 'sale_position' => $position])->save();
            $lots[$publicId]->auction()->update(['ends_at' => $sale->closingFor($position), 'original_ends_at' => $sale->closingFor($position)]);
        }

        // Wat Ivy deze week zag: observaties bij de eigen kavels.
        foreach ([
            ['nova', 'Instagram levert bij "'.$lots['7701']->title.'" de meest relevante bezoekers. Laat de sluittijd staan.', 'De meeste biedingen komen bij deze interesse in het laatste uur.', $lots['7701']],
            ['atlas', 'De zit-sta bureaus staan nu 55% onder de waarde-indicatie.', 'Vergelijkbare partijen eindigden de laatste drie maanden tussen € 1.500 en € 2.100.', $lots['7703']],
            ['lex', 'Alle documenten bij de combisteamer zijn gecontroleerd en leesbaar.', 'Onderhoudshistorie aanwezig: dat geeft kopers zekerheid.', $lots['7706']],
            ['flow', 'Drie kopers vroegen een transportindicatie op voor de barkrukken.', 'Een vast transporttarief in de advertentie haalt twijfel weg.', $lots['7702']],
        ] as [$agent, $title, $body, $lot]) {
            AiInsight::firstOrCreate(
                ['user_id' => $this->anna->id, 'type' => 'weekly', 'title' => $title],
                ['agent' => $agent, 'body' => $body, 'severity' => InsightSeverity::Info, 'action_params' => ['lot' => $lot->slug], 'valid_until' => now()->addDays(7)],
            );
        }
    }

    /**
     * Een jaar verkopen voor de omzetgrafiek, plus recente orders in elke
     * staat: betaald (te verzenden), onderweg, geleverd en afgerond.
     */
    private function salesHistory(): void
    {
        $titles = ['Bureaustoelen Herman Miller, 8 stuks', 'Akoestische wandpanelen, 30 m²', 'Kastenwand eiken fineer, 6 meter', 'Vaatwasser Winterhalter doorschuif', 'Hanglampen messing, set van 12', 'Vergaderstoelen Vitra, 16 stuks', 'Koffiemolen Mahlkönig E65S', 'Lockerkast 24 vakken', 'Barista-werkbank RVS met spoelbak', 'Projectvloer eiken visgraat, 80 m²', 'Flexplekken met scheidingswand, 10 stuks', 'IJsblokjesmachine Hoshizaki', 'Loungebank velours, 4 meter', 'Presentatiescherm 86 inch met standaard', 'Roltafels hoog, set van 6', 'Bakoven Unox 4 niveaus', 'Tuinset teak voor dakterras', 'Archiefkasten staal, 9 stuks'];

        // [dagen geleden afgerond, hamerprijs, status]
        $rows = [
            [340, 180_000, 'completed'], [310, 240_000, 'completed'], [282, 210_000, 'completed'], [250, 320_000, 'completed'],
            [221, 290_000, 'completed'], [190, 380_000, 'completed'], [160, 410_000, 'completed'], [128, 360_000, 'completed'],
            [97, 470_000, 'completed'], [66, 520_000, 'completed'], [48, 440_000, 'completed'], [36, 590_000, 'completed'],
            [19, 610_000, 'completed'], [8, 540_000, 'released'], [3, 680_000, 'released'],
            [2, 310_000, 'delivered'], [1, 265_000, 'shipped'], [0, 395_000, 'paid'],
        ];

        foreach ($rows as $index => [$daysAgo, $hammer, $state]) {
            $number = 'ANNA-V'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $buyer = $this->bidders[$index % count($this->bidders)];
            $moment = now()->subDays($daysAgo)->setTime(11, 30);

            $lot = $this->lot($this->anna, $this->company, [
                'public_id' => '77'.(string) (20 + $index),
                'title' => $titles[$index],
                'description' => 'Verkocht via Aucvy. Opgehaald in Amersfoort.',
                'category' => $index % 3 === 0 ? 'horeca-keuken' : 'kantoor-inventaris',
                'status' => LotStatus::Sold,
                'condition' => LotCondition::Good,
                'vat_regime' => VatRegime::Margin,
                'value_min_cents' => (int) round($hammer * 0.9),
                'value_max_cents' => (int) round($hammer * 1.25),
                'value_confidence' => ValueConfidence::Medium,
                'completeness_score' => 90,
                'published_at' => $moment->copy()->subDays(12),
                'created_at' => $moment->copy()->subDays(14),
            ]);

            if ($lot->documents()->count() === 0) {
                LotDocument::factory()->checkedByIvy()->create(['lot_id' => $lot->id]);
            }

            $status = match ($state) {
                'paid' => OrderStatus::Paid,
                'shipped' => OrderStatus::Shipped,
                'delivered' => OrderStatus::Delivered,
                default => OrderStatus::Completed,
            };
            $adminFee = PlatformFacts::adminFeeCents();
            $adminVat = Money::percentageOf($adminFee, Money::vatRatePercent());

            $order = Order::query()->where('order_number', $number)->first() ?? Order::factory()->create([
                'order_number' => $number,
                'lot_id' => $lot->id,
                'buyer_id' => $buyer->id,
                'seller_id' => $this->anna->id,
                'hammer_cents' => $hammer,
                'transport_cents' => 0,
                'total_cents' => $hammer + $adminFee + $adminVat,
                'status' => $status,
                'created_at' => $moment->copy()->subDays(6),
                'payment_deadline_at' => $moment->copy()->subDays(4),
                'paid_at' => $moment->copy()->subDays(5),
                'shipped_at' => $state === 'paid' ? null : $moment->copy()->subDays(3),
                'delivered_at' => in_array($state, ['paid', 'shipped'], true) ? null : $moment->copy()->subDays(2),
                'dispute_window_ends_at' => in_array($state, ['paid', 'shipped'], true) ? null : ($state === 'delivered' ? now()->addHours(30) : $moment->copy()->subDay()),
                'completed_at' => in_array($state, ['completed', 'released'], true) ? $moment : null,
            ]);

            $fee = Money::payoutFor($hammer, $this->company)->feeCents;
            Payout::updateOrCreate(['order_id' => $order->id], [
                'seller_id' => $this->anna->id,
                'amount_cents' => $hammer - $fee,
                'fee_cents' => $fee,
                'status' => match ($state) {
                    'paid', 'shipped' => PayoutStatus::Scheduled,
                    'delivered' => PayoutStatus::Held,
                    'released' => PayoutStatus::Released,
                    default => PayoutStatus::Paid,
                },
                'hold_until' => $state === 'delivered' ? now()->addHours(30) : ($order->delivered_at?->copy()->addDays(2)),
                'released_at' => in_array($state, ['completed', 'released'], true) ? $moment->copy()->addHours(2) : null,
            ]);

            $this->invoices($order);

            if (in_array($state, ['completed', 'released'], true) && $order->review()->doesntExist() && $index % 4 !== 3) {
                Review::factory()->create([
                    'order_id' => $order->id,
                    'reviewer_id' => $buyer->id,
                    'seller_id' => $this->anna->id,
                    'company_id' => $this->company->id,
                    'rating' => [5, 4, 5, 5, 4, 5][$index % 6],
                    'body' => ['Precies zoals omschreven, netjes klaargezet.', 'Goede communicatie, ophalen ging vlot.', 'Mooie partij, eerlijk over de gebruikssporen.', 'Snel geregeld. Zeker voor herhaling vatbaar.', 'Alles compleet, inclusief de papieren.', 'Top verkoper, hielp zelfs met laden.'][$index % 6],
                    'published_at' => $moment->copy()->addDay(),
                ]);
            }
        }
    }

    /**
     * Drie extra veilingen, zodat elk filter op de veilingenpagina iets
     * toont: een geplande, een concept en een afgeronde met biedingen.
     */
    private function extraSales(): void
    {
        $sale = fn (string $publicId, string $title, array $attributes): Sale => Sale::updateOrCreate(
            ['slug' => Str::slug($title).'-'.$publicId],
            array_merge([
                'public_id' => $publicId,
                'seller_id' => $this->anna->id,
                'company_id' => $this->company->id,
                'title' => $title,
                'description' => 'Demoveiling van Bakker Interieur.',
                'closing_gap_seconds' => 120,
            ], $attributes),
        );

        // Gepland: gepubliceerd, de biedronde is nog niet open.
        $planned = $sale('7791', 'Complete inventaris koffiebar', [
            'ends_at' => now()->addDays(9)->setTime(20, 0),
            'viewing_at' => now()->addDays(7)->setTime(14, 0),
            'viewing_note' => 'Op afspraak; het adres volgt per mail na bevestiging.',
            'published_at' => now()->subDay(),
        ]);

        foreach ([['7741', 'Koffiebar-toonbank eiken met RVS werkblad, 4 meter'], ['7742', 'Bonenmolens Mazzer, set van 3'], ['7743', 'Barkrukken en statafels, 14-delig']] as $position => [$publicId, $title]) {
            $lot = $this->lot($this->anna, $this->company, [
                'public_id' => $publicId,
                'title' => $title,
                'description' => 'Uit de koffiebar in de showroom. Komt mee in de veiling van de complete inventaris.',
                'category' => 'horeca-keuken',
                'status' => LotStatus::Scheduled,
                'condition' => LotCondition::Good,
                'completeness_score' => 84,
                'published_at' => null,
                'sale_id' => $planned->id,
                'sale_position' => $position,
            ]);
            $this->signals($lot, 40 + $position * 22, 9 + $position * 4, 2 + $position, 1);
        }

        // Concept: nog niet gepubliceerd.
        $draft = $sale('7792', 'Heftruck en magazijninrichting', [
            'ends_at' => now()->addDays(21)->setTime(20, 0),
            'published_at' => null,
        ]);

        foreach ([['7744', 'Elektrische heftruck Linde E16, 2019'], ['7745', 'Magazijnstellingen 60 strekkende meter']] as $position => [$publicId, $title]) {
            $this->lot($this->anna, $this->company, [
                'public_id' => $publicId,
                'title' => $title,
                'description' => 'Concept: foto en documenten volgen.',
                'category' => 'machines-techniek',
                'status' => LotStatus::Draft,
                'condition' => LotCondition::Used,
                'completeness_score' => 58,
                'published_at' => null,
                'sale_id' => $draft->id,
                'sale_position' => $position,
            ]);
        }

        // Afgerond: drie verkochte kavels uit de historie, met een gesloten biedronde.
        $closedAt = now()->subDays(45)->setTime(20, 0);
        $closed = $sale('7793', 'Kantoorinrichting voorjaar', [
            'ends_at' => $closedAt,
            'published_at' => $closedAt->copy()->subDays(10),
        ]);

        foreach (['7729', '7730', '7731'] as $position => $publicId) {
            $lot = Lot::query()->where('public_id', $publicId)->with('order')->first();

            if ($lot === null) {
                continue;
            }

            $lot->forceFill(['sale_id' => $closed->id, 'sale_position' => $position])->save();
            $hammer = (int) ($lot->order?->hammer_cents ?? 300_000);

            Auction::firstOrCreate(['lot_id' => $lot->id], [
                'starts_at' => $closedAt->copy()->subDays(10),
                'ends_at' => $closed->closingFor($position),
                'original_ends_at' => $closed->closingFor($position),
                'closed_at' => $closed->closingFor($position),
                'start_price_cents' => (int) round($hammer * 0.4),
                'current_price_cents' => $hammer,
                'bid_count' => 38 + $position * 21,
                'bidder_count' => 7 + $position * 2,
                'increment_table' => config('aucvy.auction.increment_table'),
                'anti_snipe_window_s' => config('aucvy.auction.anti_snipe_window_seconds'),
                'anti_snipe_extend_s' => config('aucvy.auction.anti_snipe_extend_seconds'),
            ]);
            $this->signals($lot, 520 + $position * 140, 38 + $position * 12, 11 + $position * 5, 8);
        }
    }

    /**
     * Als koper: de lopende veilingen weer een sluittijd in de toekomst
     * geven, op twee ervan weer bovenaan komen, en een volle volglijst.
     */
    private function buyerAuctions(): void
    {
        $guards = app(BidGuards::class);
        $ownLotIds = Lot::query()->where('seller_id', $this->anna->id)->pluck('id');

        // Twee veilingen zonder andere bieders: daar staat Anna meteen bovenaan.
        foreach (['9201', '9203'] as $offset => $publicId) {
            $quiet = Auction::query()->whereNull('closed_at')->whereHas('lot', fn ($query) => $query->where('public_id', $publicId))->first();

            if ($quiet === null || Bid::query()->where('auction_id', $quiet->id)->where('user_id', $this->anna->id)->exists()) {
                continue;
            }

            if ($quiet->ends_at->isPast()) {
                $endsAt = now()->addHours(20 + $offset * 30)->addMinutes(8);
                $quiet->forceFill(['ends_at' => $endsAt, 'original_ends_at' => $endsAt])->save();
            }

            try {
                app(PlaceBid::class)->handle(auction: $quiet->fresh(), user: $this->anna, amountCents: $guards->minimumBid($quiet->fresh()), idempotencyKey: 'anna-demo:koper:'.$quiet->id.':eerste');
            } catch (Throwable $exception) {
                $this->command?->warn('Eerste bod van Anna overgeslagen: '.$exception->getMessage());
            }
        }

        $auctions = Auction::query()
            ->whereIn('id', Bid::query()->where('user_id', $this->anna->id)->select('auction_id'))
            ->whereNull('closed_at')
            ->whereNotIn('lot_id', $ownLotIds)
            ->with('lot')
            ->orderBy('id')
            ->get();

        $leading = 0;

        foreach ($auctions as $index => $auction) {
            if ($auction->ends_at->isPast()) {
                $endsAt = now()->addHours([7, 26, 52, 3, 98][$index % 5])->addMinutes(12);
                $auction->forceFill(['ends_at' => $endsAt, 'original_ends_at' => $endsAt])->save();
            }

            $lot = $auction->lot;

            if ($lot !== null && ! $lot->hasValueEstimate()) {
                $price = max(10_000, $auction->current_price_cents);
                $lot->forceFill(['value_min_cents' => (int) round($price * 1.15), 'value_max_cents' => (int) round($price * 1.6), 'value_confidence' => ValueConfidence::Medium])->save();
            }

            if ($lot !== null) {
                $this->signals($lot, 180 + $index * 140, 14 + $index * 9, 5 + $index * 3, 4 + $index);
            }

            // Op twee veilingen komt Anna weer bovenaan; de rest blijft "overboden".
            // Een automatisch bod van een ander kan haar direct weer overbieden,
            // dus ze probeert het een paar keer. Nooit boven de waarde-indicatie:
            // anders jaagt elke nieuwe run de prijs verder op.
            $leads = fn (): bool => Bid::query()->where('auction_id', $auction->id)->where('status', BidStatus::Winning)->where('user_id', $this->anna->id)->exists();
            $ceiling = $lot?->value_max_cents ?? 0;

            if ($leads()) {
                $leading++;
            }

            for ($attempt = 0; $leading < 2 && $attempt < 14 && ! $leads(); $attempt++) {
                $fresh = $auction->fresh();

                if ($guards->minimumBid($fresh) > $ceiling) {
                    break;
                }

                try {
                    app(PlaceBid::class)->handle(auction: $fresh, user: $this->anna, amountCents: $guards->minimumBid($fresh), idempotencyKey: 'anna-demo:koper:'.$auction->id.':'.$fresh->bid_count);
                } catch (Throwable $exception) {
                    $this->command?->warn('Bod van Anna overgeslagen: '.$exception->getMessage());

                    break;
                }

                if ($leads()) {
                    $leading++;
                }
            }
        }

        if ($auctions->isNotEmpty() && $auctions->first()->lot !== null) {
            $auctions->first()->lot->forceFill(['is_hot' => true, 'hot_since' => now()->subHours(5)])->save();
        }

        // Volglijst: live kavels van anderen, verschillende verkoopvormen.
        $watch = Lot::query()
            ->whereIn('status', [LotStatus::Live, LotStatus::Closing])
            ->whereNotIn('seller_id', [$this->anna->id])
            ->whereHas('auction', fn ($query) => $query->whereNull('closed_at'))
            ->with('auction')
            ->orderBy('id')
            ->limit(60)
            ->get()
            ->groupBy(fn (Lot $lot): string => $lot->sale_type->value)
            ->flatMap(fn ($group) => $group->take(3))
            ->take(7);

        foreach ($watch->values() as $index => $lot) {
            WatchlistItem::firstOrCreate(['user_id' => $this->anna->id, 'lot_id' => $lot->id]);

            if ($lot->auction !== null && $lot->auction->ends_at->isPast()) {
                $endsAt = now()->addHours(4 + $index * 17);
                $lot->auction->forceFill(['ends_at' => $endsAt, 'original_ends_at' => $endsAt, 'starts_at' => $lot->sale_type === SaleType::Dutch ? now()->subMinutes(5) : $lot->auction->starts_at])->save();
            }

            $this->signals($lot, 240 + $index * 95, 18 + $index * 6, 6 + $index * 2, 3 + $index);
        }
    }

    /**
     * Bestellingen als koper in elke staat: te betalen (met een termijn in
     * de toekomst), onderweg, geleverd en een geschil. Met facturen.
     */
    private function buyerOrders(): void
    {
        // Openstaande betalingen krijgen weer een termijn die nog loopt.
        Order::query()
            ->where('buyer_id', $this->anna->id)
            ->where('status', OrderStatus::AwaitingPayment)
            ->where('payment_deadline_at', '<', now())
            ->get()
            ->each(fn (Order $order, int $index) => $order->forceFill(['payment_deadline_at' => now()->addHours(30 + $index * 12), 'created_at' => now()->subHours(16 + $index * 3)])->save());

        $seller = $this->otherSeller;
        $sellerCompany = $seller->company;

        $rows = [
            ['7751', 'ANNA-K01', 'Rational combi-oven SCC 61', 'horeca-keuken-kookapparatuur', 245_000, 250_000, 350_000, 'shipped'],
            ['7752', 'ANNA-K02', 'La Marzocco espressomachine, 1-groeps', 'horeca-keuken-kookapparatuur', 120_000, 100_000, 160_000, 'delivered'],
            ['7753', 'ANNA-K03', 'Planetaire mixer 10 liter', 'horeca-keuken-kookapparatuur', 27_500, 30_000, 45_000, 'disputed'],
            ['7754', 'ANNA-K04', 'Werkbank RVS met onderblad, 180 cm', 'horeca-keuken', 38_000, 45_000, 65_000, 'completed'],
        ];

        foreach ($rows as $index => [$publicId, $number, $title, $category, $hammer, $min, $max, $state]) {
            $moment = now()->subDays([2, 3, 6, 21][$index])->setTime(15, 10);

            $lot = $this->lot($seller, $sellerCompany, [
                'public_id' => $publicId,
                'title' => $title,
                'description' => 'Gewonnen door Anna via de veiling. Opgehaald in Utrecht.',
                'category' => $category,
                'status' => LotStatus::Sold,
                'condition' => LotCondition::Good,
                'vat_regime' => VatRegime::Margin,
                'value_min_cents' => $min,
                'value_max_cents' => $max,
                'value_confidence' => ValueConfidence::High,
                'completeness_score' => 92,
                'published_at' => $moment->copy()->subDays(8),
            ]);

            $adminFee = PlatformFacts::adminFeeCents();
            $adminVat = Money::percentageOf($adminFee, Money::vatRatePercent());

            $order = Order::query()->where('order_number', $number)->first() ?? Order::factory()->create([
                'order_number' => $number,
                'lot_id' => $lot->id,
                'buyer_id' => $this->anna->id,
                'seller_id' => $seller->id,
                'hammer_cents' => $hammer,
                'transport_cents' => 0,
                'total_cents' => $hammer + $adminFee + $adminVat,
                'status' => match ($state) {
                    'shipped' => OrderStatus::Shipped,
                    'delivered' => OrderStatus::Delivered,
                    'disputed' => OrderStatus::Disputed,
                    default => OrderStatus::Completed,
                },
                'created_at' => $moment->copy()->subDays(2),
                'payment_deadline_at' => $moment->copy(),
                'paid_at' => $moment->copy()->subDay(),
                'shipped_at' => $moment,
                'delivered_at' => $state === 'shipped' ? null : $moment->copy()->addDay(),
                'dispute_window_ends_at' => match ($state) {
                    'shipped' => null,
                    'delivered' => now()->addHours(26),
                    default => $moment->copy()->addDays(3),
                },
                'completed_at' => $state === 'completed' ? $moment->copy()->addDays(4) : null,
            ]);

            $fee = Money::payoutFor($hammer, $sellerCompany)->feeCents;
            Payout::firstOrCreate(['order_id' => $order->id], [
                'seller_id' => $seller->id,
                'amount_cents' => $hammer - $fee,
                'fee_cents' => $fee,
                'status' => $state === 'completed' ? PayoutStatus::Paid : PayoutStatus::Held,
                'hold_until' => now()->addDays(2),
            ]);

            $this->invoices($order);

            if ($state === 'disputed') {
                Dispute::firstOrCreate(['order_id' => $order->id], [
                    'reported_by' => $this->anna->id,
                    'reason' => 'damaged',
                    'description' => 'De kom heeft een deuk en de garde ontbreekt. Op de foto\'s bij de kavel was de mixer compleet.',
                    'status' => DisputeStatus::Reported,
                    'sla_due_at' => now()->addHours(40),
                ]);
            }

            if ($state === 'completed' && $order->review()->doesntExist()) {
                Review::factory()->create([
                    'order_id' => $order->id,
                    'reviewer_id' => $this->anna->id,
                    'seller_id' => $seller->id,
                    'company_id' => $sellerCompany?->id,
                    'rating' => 5,
                    'body' => 'Stond netjes klaar en was precies zoals op de foto\'s.',
                    'published_at' => $moment->copy()->addDays(5),
                ]);
            }
        }

        // De oudere aankopen: een waarde-indicatie erbij voor "Hoe je kocht", en facturen.
        Order::query()->where('buyer_id', $this->anna->id)->with('lot')->get()->each(function (Order $order): void {
            if ($order->lot !== null && ! $order->lot->hasValueEstimate()) {
                $order->lot->forceFill([
                    'value_min_cents' => (int) round($order->hammer_cents * 1.05),
                    'value_max_cents' => (int) round($order->hammer_cents * 1.45),
                    'value_confidence' => ValueConfidence::Medium,
                ])->save();
            }

            if ($order->paid_at !== null) {
                $this->invoices($order);
            }
        });
    }

    /**
     * Zoekalerts, meldingen, gesprekken, een supportvraag en wat Ivy zag.
     *
     * @param  array<string, Lot>  $lots
     */
    private function inbox(array $lots): void
    {
        foreach ([
            ['Horeca-koeling tot € 1.500', ['q' => 'koelcel', 'tot' => '1500']],
            ['Kantoormeubilair, laatste kans', ['categorie' => 'kantoor-inventaris', 'toon' => 'laatste-kans']],
            ['Espressomachines', ['q' => 'espressomachine']],
        ] as [$name, $query]) {
            SavedSearch::firstOrCreate(
                ['user_id' => $this->anna->id, 'name' => $name],
                ['query' => $query, 'channel' => NotificationChannel::Email, 'last_run_at' => now()->subHours(3)],
            );
        }

        // Meldingen: eerst de eerdere demomeldingen weg, dan een verse set.
        $this->anna->notifications()->where('data', 'like', '%"demo":true%')->delete();
        $openOrder = Order::query()->where('buyer_id', $this->anna->id)->where('status', OrderStatus::AwaitingPayment)->first();

        foreach ([
            [OutbidPush::class, 'outbid', 'Je bent overboden op Complete designkeuken met Siemens-apparatuur', 'De veiling loopt nog. Bied opnieuw of laat hem lopen.', route('dashboard.bids'), 25, false],
            [ViewingRequested::class, 'viewing', 'Nieuwe bezichtigingsaanvraag voor '.$lots['7701']->title, 'Een koper stelt een moment voor. Bevestig of stel een ander moment voor.', route('dashboard.lots.show', $lots['7701']), 70, false],
            [OrderStatusChanged::class, 'order', 'Betaald: Presentatiescherm 86 inch met standaard', 'Regel de levering: zet hem klaar of meld hem verzonden.', route('orders.index'), 190, false],
            [PayoutReleased::class, 'payout', 'Uitbetaling vrijgegeven', 'De houdtermijn is verstreken; het bedrag is onderweg naar je rekening.', route('dashboard.payouts'), 410, false],
            [AlertMatches::class, 'alert', '4 nieuwe kavels voor je alert "Horeca-koeling tot € 1.500"', 'Twee koelcellen en twee koelwerkbanken, allemaal in Utrecht.', route('dashboard.alerts'), 560, true],
            [WatchlistEnding::class, 'watchlist', 'Een kavel op je volglijst sluit binnen een uur', 'Wie nu biedt, verlengt de veiling met een minuut.', route('dashboard.watchlist'), 800, true],
            [OrderStatusChanged::class, 'order', 'Je bestelling is onderweg', 'Rational combi-oven SCC 61 is verzonden. Bevestig de levering als alles binnen is.', route('orders.index'), 1_300, true],
            [VerificationDecided::class, 'verification', 'Je bedrijf is geverifieerd', 'KvK en rekening zijn gecontroleerd. Je kunt kavels publiceren.', route('organisation.show'), 9_000, true],
            [OrderStatusChanged::class, 'order', $openOrder !== null ? 'Betaal je bestelling '.$openOrder->order_number : 'Je betaling is ontvangen', 'De betaaltermijn loopt; daarna vervalt de koop.', route('orders.index'), 130, false],
        ] as [$type, $kind, $title, $body, $url, $minutesAgo, $read]) {
            $this->anna->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $type,
                'data' => ['kind' => $kind, 'title' => $title, 'body' => $body, 'url' => $url, 'demo' => true],
                'read_at' => $read ? now()->subMinutes(max(1, $minutesAgo - 30)) : null,
                'created_at' => now()->subMinutes($minutesAgo),
                'updated_at' => now()->subMinutes($minutesAgo),
            ]);
        }

        // Gesprekken: twee als verkoper, een als koper.
        $kitchen = Lot::query()->where('public_id', '8842')->first();

        $threads = [
            [$lots['7701'], $this->bidders[0], $this->anna, Conversation::KIND_QUESTION, 'documenten', true, false, [
                ['buyer', 'Staat het serienummer van de machine ook op de aankoopfactuur?', 190],
                ['seller', 'Ja, op pagina 1 onder de omschrijving. De onderhoudshistorie staat er ook bij.', 150],
                ['buyer', 'Dank. Kan ik zaterdag komen kijken en hem zien draaien?', 35],
            ]],
            [$lots['7702'], $this->bidders[1], $this->anna, Conversation::KIND_CHAT, null, false, true, [
                ['buyer', 'Zijn de krukken ook per twaalf te koop?', 2_900],
                ['seller', 'Nee, alleen als partij van 24. Zo staat het ook in de advertentie.', 2_840],
                ['system', 'Een medewerker van Aucvy leest mee.', 2_800],
                ['buyer', 'Helder. Dan bied ik op de hele partij.', 2_700],
            ]],
        ];

        if ($kitchen !== null) {
            $threads[] = [$kitchen, $this->anna, $this->otherSeller, Conversation::KIND_QUESTION, 'ophalen', false, false, [
                ['buyer', 'Is demontage inbegrepen, of moet ik zelf iemand meenemen?', 1_500],
                ['seller', 'Demontage doe je zelf. We hebben een takel en een palletwagen staan die je mag gebruiken.', 1_380],
                ['buyer', 'Prima. Dan plan ik het ophalen voor volgende week donderdag.', 1_300],
                ['seller', 'Donderdag kan tussen 9 en 16 uur. Meld je bij het magazijn.', 60],
            ]];
        }

        $staff = User::query()->where('is_staff', true)->first();

        foreach ($threads as [$lot, $buyer, $seller, $kind, $part, $unreadForSeller, $withOperator, $messages]) {
            $conversation = Conversation::firstOrCreate(
                ['lot_id' => $lot->id, 'buyer_id' => $buyer->id, 'kind' => $kind],
                ['seller_id' => $seller->id, 'company_id' => $seller->company_id, 'status' => Conversation::STATUS_OPEN, 'lot_part' => $part],
            );

            if ($conversation->messages()->count() > 0) {
                continue;
            }

            foreach ($messages as [$from, $body, $minutesAgo]) {
                ConversationMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_type' => match ($from) {
                        'buyer' => ConversationMessage::FROM_BUYER,
                        'seller' => ConversationMessage::FROM_SELLER,
                        default => ConversationMessage::FROM_SYSTEM,
                    },
                    'sender_id' => match ($from) {
                        'buyer' => $buyer->id,
                        'seller' => $seller->id,
                        default => null,
                    },
                    'body' => $body,
                    'state' => ConversationMessage::VISIBLE,
                    'created_at' => now()->subMinutes($minutesAgo),
                ]);
            }

            $last = now()->subMinutes(min(array_column($messages, 2)));
            $annaIsSeller = $seller->is($this->anna);
            $conversation->forceFill([
                'last_message_at' => $last,
                'buyer_read_at' => $annaIsSeller ? $last : $last->copy()->subMinutes(5),
                'seller_read_at' => $annaIsSeller && $unreadForSeller ? $last->copy()->subMinutes(120) : $last,
                'operator_id' => $withOperator ? $staff?->id : null,
                'operator_joined_at' => $withOperator && $staff !== null ? now()->subMinutes(2_800) : null,
            ])->save();
        }

        if (SupportTicket::query()->where('user_id', $this->anna->id)->doesntExist()) {
            SupportTicket::factory()->create([
                'user_id' => $this->anna->id,
                'name' => $this->anna->name,
                'email' => $this->anna->email,
                'subject' => 'Kan ik de ophaalperiode van een veiling verlengen?',
                'message' => 'De showroom is op vrijdag dicht. Kan de ophaalperiode een dag langer?',
                'created_at' => now()->subHours(20),
            ]);
        }

        // Wat Ivy zag voor de koper Anna.
        foreach ([
            ['ivy', 'Vond 4 nieuwe matches voor je alert "Horeca-koeling tot € 1.500"', 'Twee daarvan sluiten deze week.'],
            ['lex', 'Controleerde de documenten van 2 gevolgde kavels', 'Bij beide is de aankoopfactuur aanwezig en leesbaar.'],
        ] as [$agent, $title, $body]) {
            AiInsight::firstOrCreate(
                ['user_id' => $this->anna->id, 'type' => 'weekly', 'title' => $title],
                ['agent' => $agent, 'body' => $body, 'severity' => InsightSeverity::Info, 'valid_until' => now()->addDays(7)],
            );
        }
    }

    /**
     * Facturen bij een betaalde order: kavel, administratiekosten en fee.
     */
    private function invoices(Order $order): void
    {
        if ($order->invoices()->exists()) {
            return;
        }

        try {
            app(Invoices::class)->forPaidOrder($order->fresh());
        } catch (Throwable $exception) {
            $this->command?->warn('Geen factuur bij '.$order->order_number.': '.$exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function lot(User $seller, ?Company $company, array $attributes): Lot
    {
        $category = Category::query()->where('slug', $attributes['category'] ?? '')->first()
            ?? Category::query()->whereNull('parent_id')->first();
        unset($attributes['category']);

        $location = $seller->is($this->anna)
            ? Location::firstOrCreate(
                ['user_id' => $seller->id, 'label' => 'Showroom Amersfoort'],
                ['company_id' => $company?->id, 'street' => 'Voorbeeldweg', 'house_number' => '27', 'postal_code' => '3811AB', 'city' => 'Amersfoort', 'province' => 'Utrecht'],
            )
            : (Location::query()->where('user_id', $seller->id)->first() ?? Location::factory()->create(['user_id' => $seller->id, 'company_id' => $company?->id]));

        $publicId = (string) $attributes['public_id'];

        return Lot::updateOrCreate(
            ['public_id' => $publicId],
            array_merge([
                'seller_id' => $seller->id,
                'company_id' => $company?->id,
                'category_id' => $category?->id,
                'location_id' => $location->id,
                'slug' => Str::slug((string) $attributes['title']).'-'.$publicId,
                'status' => LotStatus::Live,
                'sale_type' => SaleType::Auction,
                'brand' => null,
                'quantity' => 1,
                'unit' => 'stuk',
                'published_at' => now()->subDays(3),
                'pickup_only' => true,
            ], $attributes),
        );
    }

    /**
     * Maakt de veiling bij een kavel, of geeft een verlopen veiling een
     * nieuwe sluittijd. De stand en de biedingen blijven staan.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function openAuction(Lot $lot, array $attributes, int $hours, bool $restart = false): Auction
    {
        $endsAt = now()->addHours($hours)->addMinutes(17);
        $auction = Auction::query()->where('lot_id', $lot->id)->first();

        if ($auction === null) {
            return Auction::create(array_merge([
                'lot_id' => $lot->id,
                'starts_at' => now()->subDays(3),
                'ends_at' => $endsAt,
                'original_ends_at' => $endsAt,
                'current_price_cents' => $attributes['start_price_cents'],
                'increment_table' => config('aucvy.auction.increment_table'),
                'anti_snipe_window_s' => config('aucvy.auction.anti_snipe_window_seconds'),
                'anti_snipe_extend_s' => config('aucvy.auction.anti_snipe_extend_seconds'),
            ], $attributes));
        }

        if ($auction->closed_at === null && ($auction->ends_at->isPast() || $restart)) {
            $auction->forceFill(array_merge(['ends_at' => $endsAt, 'original_ends_at' => $endsAt], $restart ? ['starts_at' => now()->subMinutes(4)] : []))->save();
        }

        return $auction;
    }
}
