<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Auctions\Actions\PlaceBid;
use App\Enums\DepositStatus;
use App\Enums\KycStatus;
use App\Enums\LotCondition;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Enums\UserType;
use App\Enums\ValueConfidence;
use App\Enums\VatRegime;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Company;
use App\Models\Deposit;
use App\Models\Location;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Speelbare demodata: kavels in alle drie de verkoopvormen, met echte
 * biedingen die via de biedmotor zijn geplaatst.
 *
 * Bewust níét via Bid::factory(): een bod ontstaat uitsluitend via PlaceBid,
 * ook hier. Zo staan de tellers, de volgnummers en de eventstroom meteen goed
 * en is de demo een eerlijke weergave van wat de motor doet (hfst. 3).
 *
 * Alleen voor lokaal en staging - draait niet mee met DatabaseSeeder.
 *
 *     php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoSeeder draait niet op productie.');

            return;
        }

        $this->call(CategorySeeder::class);

        [$seller, $company] = $this->seller();
        $bidders = $this->bidders();

        $this->kitchenAuction($seller, $company, $bidders);
        $this->excavatorAuction($seller, $company, $bidders);
        $this->dutchAuction($seller, $company, $bidders);
        $this->buyNowLot($seller, $company);
        // Bewust onder de borgdrempel: laat ook de route zónder borg zien.
        $this->quietAuction($seller, $company);

        $this->command?->newLine();
        $this->command?->info('Demodata klaar. Inloggen kan met:');
        $this->command?->table(
            ['E-mail', 'Wachtwoord', 'Rol'],
            [
                ['verkoper@aucvy.test', 'wachtwoord', 'verkoper (kan niet op eigen kavels bieden)'],
                ['anna@aucvy.test', 'wachtwoord', 'geverifieerde bieder'],
                ['bram@aucvy.test', 'wachtwoord', 'geverifieerde bieder'],
                ['nieuw@aucvy.test', 'wachtwoord', 'niet geverifieerd - ziet de verificatiestaat'],
            ],
        );
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function seller(): array
    {
        $company = Company::firstOrCreate(
            ['slug' => 'keukenhuis-utrecht'],
            [
                'name' => 'Keukenhuis Utrecht BV',
                'kvk_number' => '30123456',
                'vat_number' => 'NL801234567B01',
                'verified_at' => now()->subMonths(14),
                'ubo_verified_at' => now()->subMonths(14),
                'payout_iban_last4' => '4821',
                'payout_iban_name' => 'Keukenhuis Utrecht BV',
            ],
        );

        $seller = User::firstOrCreate(
            ['email' => 'verkoper@aucvy.test'],
            [
                'name' => 'Sander Vermeulen',
                'display_name' => 'Keukenhuis Utrecht',
                'company_id' => $company->id,
                'type' => UserType::Business,
                'password' => 'wachtwoord',
                'email_verified_at' => now(),
                'kyc_status' => KycStatus::Verified,
                'kyc_verified_at' => now()->subMonths(14),
                'can_bid' => true,
            ],
        );

        return [$seller, $company];
    }

    /**
     * @return array<string, User>
     */
    private function bidders(): array
    {
        $make = fn (string $email, string $name, bool $verified): User => User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'display_name' => null,
                'password' => 'wachtwoord',
                'email_verified_at' => now(),
                'kyc_status' => $verified
                    ? KycStatus::Verified
                    : KycStatus::Unverified,
                'kyc_verified_at' => $verified ? now()->subMonths(3) : null,
                'can_bid' => $verified,
            ],
        );

        // De medewerker uit de geschilroute (stap 12) - mag bij /beheer.
        $staff = User::firstOrCreate(
            ['email' => 'beheer@aucvy.test'],
            [
                'name' => 'Beheer Medewerker',
                'display_name' => null,
                'password' => 'wachtwoord',
                'email_verified_at' => now(),
                'kyc_status' => KycStatus::Verified,
                'kyc_verified_at' => now(),
                'can_bid' => false,
            ],
        );
        $staff->is_staff = true;
        $staff->save();

        return [
            'anna' => $make('anna@aucvy.test', 'Anna Bakker', true),
            'bram' => $make('bram@aucvy.test', 'Bram Klaassen', true),
            'carla' => $make('carla@aucvy.test', 'Carla de Vries', true),
            // Zonder verificatie: laat de "je moet je eerst verifiëren"-staat
            // van het biedpaneel zien.
            'nieuw' => $make('nieuw@aucvy.test', 'Nieuwe Gebruiker', false),
        ];
    }

    /**
     * Het kavel uit het ontwerp: kavel 8842, de designkeuken.
     *
     * @param  array<string, User>  $bidders
     */
    private function kitchenAuction(User $seller, Company $company, array $bidders): void
    {
        $lot = $this->lot($seller, $company, [
            'public_id' => '8842',
            'title' => 'Complete designkeuken met Siemens-apparatuur',
            'description' => 'Complete keuken uit een showroom, drie jaar oud. '
                .'Composieten werkblad met enkele lichte gebruikssporen aan de '
                .'linkerzijde, zichtbaar op foto 8 en 9. Apparatuur volledig '
                .'werkend, inclusief handleidingen en garantiebewijzen.',
            'category' => 'horeca-keuken-complete-keukens',
            'brand' => 'Siemens',
            'year' => 2022,
            'condition' => LotCondition::Good,
            'vat_regime' => VatRegime::Margin,
            'value_min_cents' => 1_500_000,
            'value_max_cents' => 1_950_000,
            'value_confidence' => ValueConfidence::Medium,
            'transport_from_cents' => 34_000,
            'viewing_available' => true,
            'completeness_score' => 94,
            'specifications' => [
                'kitchen_brand' => ['value' => 'Siemens', 'source' => 'seller'],
                'width_cm' => ['value' => 420, 'source' => 'seller'],
                'worktop_material' => ['value' => 'Composiet', 'source' => 'ai', 'confidence' => 0.82],
                'includes_appliances' => ['value' => true, 'source' => 'seller'],
            ],
        ]);

        $auction = $this->auction($lot, [
            'start_price_cents' => 800_000,
            'reserve_price_cents' => 1_200_000,
            // Nog ruim de tijd, zodat je zelf kunt bieden zonder haast.
            'ends_at' => now()->addDays(2)->addHours(4),
        ]);

        // Echte biedingen via de motor, zodat de historie klopt.
        $this->bid($auction, $bidders['anna'], 800_000);
        $this->bid($auction, $bidders['bram'], 950_000);
        $this->bid($auction, $bidders['carla'], 1_100_000);
        $this->bid($auction, $bidders['anna'], 1_235_000);
        $this->bid($auction, $bidders['bram'], 1_285_000);

        $this->authoriseDeposits($auction, $bidders);
    }

    /**
     * Een kavel in de slotfase, zodat je anti-sniping kunt zien werken.
     *
     * @param  array<string, User>  $bidders
     */
    private function excavatorAuction(User $seller, Company $company, array $bidders): void
    {
        $lot = $this->lot($seller, $company, [
            'public_id' => '9117',
            'title' => 'Minigraafmachine 3,5 ton, 4.200 draaiuren',
            'description' => 'Rupsonderstel, laatste keuring februari. '
                .'Hydrauliek recent nagekeken, geen lekkage. Wordt geleverd met '
                .'drie bakken.',
            'category' => 'machines-techniek-graafmachines',
            'brand' => 'Kubota',
            'year' => 2019,
            'condition' => LotCondition::Good,
            'vat_regime' => VatRegime::Vat21,
            'value_min_cents' => 2_800_000,
            'value_max_cents' => 3_400_000,
            'value_confidence' => ValueConfidence::High,
            'viewing_available' => true,
            'completeness_score' => 88,
            'specifications' => [
                'operating_hours' => ['value' => 4200, 'source' => 'seller'],
                'build_year' => ['value' => 2019, 'source' => 'seller'],
                'weight_kg' => ['value' => 3500, 'source' => 'ai', 'confidence' => 0.91],
                'track_type' => ['value' => 'Rups', 'source' => 'seller'],
            ],
        ]);

        $auction = $this->auction($lot, [
            'start_price_cents' => 1_500_000,
            // Binnen het slotvenster: het volgende bod verlengt zichtbaar.
            'ends_at' => now()->addSeconds(25),
        ]);

        $this->bid($auction, $bidders['carla'], 1_500_000);
        $this->bid($auction, $bidders['anna'], 1_800_000);

        $this->authoriseDeposits($auction, $bidders);
    }

    /**
     * @param  array<string, User>  $bidders
     */
    private function dutchAuction(User $seller, Company $company, array $bidders): void
    {
        $lot = $this->lot($seller, $company, [
            'public_id' => '7734',
            'title' => 'Horeca-koelcel 12 m³ met compressor',
            'description' => 'Demontabele koelcel, in 2021 geplaatst. '
                .'Compressor draait, thermostaat vervangen in 2024.',
            'category' => 'horeca-keuken-koeling-vriescellen',
            'sale_type' => SaleType::Dutch,
            'condition' => LotCondition::Good,
            'vat_regime' => VatRegime::Vat21,
            'value_min_cents' => 450_000,
            'value_max_cents' => 650_000,
            'value_confidence' => ValueConfidence::Medium,
            'completeness_score' => 71,
        ]);

        $auction = $this->auction($lot, [
            'start_price_cents' => 2_000_000,
            'ends_at' => now()->addHours(6),
            // Elke 20 seconden € 250 eraf, bodem € 500.
            'dutch_start_cents' => 2_000_000,
            'dutch_floor_cents' => 50_000,
            'dutch_step_cents' => 25_000,
            'dutch_interval_s' => 20,
            'current_price_cents' => 2_000_000,
            'starts_at' => now()->subMinutes(3),
        ]);

        $this->authoriseDeposits($auction, $bidders);
    }

    private function buyNowLot(User $seller, Company $company): void
    {
        $lot = $this->lot($seller, $company, [
            'public_id' => '6203',
            'title' => 'Partij 40 bureaustoelen, ergonomisch',
            'description' => 'Uit een kantoorontruiming. Allemaal getest, '
                .'twee stuks met een beschadigde armleuning.',
            'category' => 'kantoor-inventaris-bureaustoelen',
            'condition' => LotCondition::Used,
            'vat_regime' => VatRegime::Margin,
            'quantity' => 40,
            'value_min_cents' => 60_000,
            'value_max_cents' => 90_000,
            'value_confidence' => ValueConfidence::Medium,
            'completeness_score' => 62,
        ]);

        $this->auction($lot, [
            'start_price_cents' => 40_000,
            'ends_at' => now()->addDays(4),
            'buy_now_price_cents' => 78_000,
            'buy_now_active' => true,
        ]);
    }

    /**
     * Een kavel zonder biedingen: de lege staat van de biedhistorie.
     */
    private function quietAuction(User $seller, Company $company): void
    {
        $lot = $this->lot($seller, $company, [
            'public_id' => '5588',
            'title' => 'Steigermateriaal, circa 120 m²',
            'description' => 'Compleet met kop- en zijleuningen. Wordt op '
                .'pallets aangeleverd, ophalen in overleg.',
            'category' => 'bouw-materieel-steigers',
            'condition' => LotCondition::Used,
            'vat_regime' => VatRegime::Vat21,
            // Lage zekerheid: laat op de veilingenpagina ook de balk
            // zónder prijsbolletje zien (hfst. 8).
            'value_min_cents' => 150_000,
            'value_max_cents' => 220_000,
            'value_confidence' => ValueConfidence::Low,
            'completeness_score' => 45,
        ]);

        $this->auction($lot, [
            'start_price_cents' => 120_000,
            'ends_at' => now()->addDays(6),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function lot(User $seller, Company $company, array $attributes): Lot
    {
        $category = Category::where('slug', $attributes['category'] ?? 'elektronica-computers')->first();
        unset($attributes['category']);

        $location = Location::firstOrCreate(
            ['user_id' => $seller->id, 'label' => 'Magazijn Utrecht'],
            [
                'company_id' => $company->id,
                'street' => 'Proefstraat',
                'house_number' => '14',
                'postal_code' => '3512AB',
                'city' => 'Utrecht',
                'province' => 'Utrecht',
            ],
        );

        $title = $attributes['title'];
        $publicId = $attributes['public_id'];

        return Lot::updateOrCreate(
            ['public_id' => $publicId],
            array_merge([
                'seller_id' => $seller->id,
                'company_id' => $company->id,
                'category_id' => $category?->id,
                'location_id' => $location->id,
                'slug' => Str::slug($title).'-'.$publicId,
                'status' => LotStatus::Live,
                'sale_type' => SaleType::Auction,
                'published_at' => now()->subDays(2),
                'pickup_only' => true,
            ], $attributes),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function auction(Lot $lot, array $attributes): Auction
    {
        $endsAt = $attributes['ends_at'];

        return Auction::updateOrCreate(
            ['lot_id' => $lot->id],
            array_merge([
                'starts_at' => now()->subDays(2),
                'original_ends_at' => $endsAt,
                'current_price_cents' => $attributes['start_price_cents'],
                // Uit config gekopieerd bij het aanmaken, daarna van de veiling
                // zelf (hfst. 5).
                'increment_table' => config('aucvy.auction.increment_table'),
                'anti_snipe_window_s' => config('aucvy.auction.anti_snipe_window_seconds'),
                'anti_snipe_extend_s' => config('aucvy.auction.anti_snipe_extend_seconds'),
            ], $attributes),
        );
    }

    private function bid(Auction $auction, User $user, int $amountCents): void
    {
        $this->ensureDeposit($auction, $user, $amountCents);

        app(PlaceBid::class)->handle(
            auction: $auction->fresh(),
            user: $user,
            amountCents: $amountCents,
            idempotencyKey: 'demo:'.$auction->id.':'.$user->id.':'.$amountCents,
        );
    }

    /**
     * Boven de drempel vraagt de motor een borg vóór het bod.
     *
     * In productie komt die van een pre-autorisatie bij Mollie; hier zetten we
     * hem rechtstreeks op `authorised`. De poortwachter blijft dus gewoon
     * werken - we simuleren alleen de betaalprovider (hfst. 9).
     */
    private function ensureDeposit(Auction $auction, User $user, int $amountCents): void
    {
        if ($amountCents <= (int) config('aucvy.deposit.threshold_cents')) {
            return;
        }

        Deposit::updateOrCreate(
            ['user_id' => $user->id, 'auction_id' => $auction->id],
            [
                'amount_cents' => 25_000,
                'provider' => 'demo',
                'provider_id' => 'demo_'.Str::random(10),
                'status' => DepositStatus::Authorised,
            ],
        );
    }

    /**
     * Geeft elke demobieder een geldige borg op deze veiling, zodat je in de
     * browser meteen kunt bieden zonder eerst een betaalprovider te hoeven
     * doorlopen.
     *
     * @param  array<string, User>  $bidders
     */
    private function authoriseDeposits(Auction $auction, array $bidders): void
    {
        foreach ($bidders as $bidder) {
            if (! $bidder->kyc_status->allowsBidding()) {
                continue;
            }

            $this->ensureDeposit($auction, $bidder, PHP_INT_MAX);
        }
    }
}
