<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceBid;
use App\Auctions\Exceptions\BidRejected;
use App\Enums\DepositStatus;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Enums\VatRegime;
use App\Models\Auction;
use App\Models\Company;
use App\Models\Deposit;
use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use App\Orders\Actions\TransitionOrder;
use App\Support\Money\Money;
use App\Support\Money\OrderCosts;
use App\Support\PlatformFacts;
use Illuminate\Support\Str;

/*
| De besluiten van Raph en Don (2 november 2026) op één plek: de staffel,
| de administratiekosten, de biedtreden, de borgtreden, de btw-keuze en de
| openbare prijzen. Eén getal in de config wijzigen is genoeg.
*/

it('kiest het tarief uit de staffel op de omzet van de verkoper', function () {
    expect(PlatformFacts::tierFor(0)['percent'])->toBe(15.0)
        ->and(PlatformFacts::tierFor(2_499_999)['percent'])->toBe(15.0)
        ->and(PlatformFacts::tierFor(2_500_000)['percent'])->toBe(12.5)
        ->and(PlatformFacts::tierFor(5_000_000)['percent'])->toBe(10.0)
        ->and(PlatformFacts::tierFor(300_000_000)['percent'])->toBe(6.0)
        ->and(PlatformFacts::nextTierFor(1_000_000))->toBe(['percent' => 12.5, 'remaining_cents' => 1_500_000])
        ->and(PlatformFacts::nextTierFor(300_000_000))->toBeNull();
});

it('rekent de fee met de staffel en legt het tarief vast op de uitbetaling', function () {
    $company = Company::factory()->create(['fee_percentage' => 3.5]);

    // Zonder omzet: 15%, wat het pakket ook zegt.
    $proceeds = Money::payoutFor(100_000, $company);
    expect($proceeds->feePercentage)->toBe(15.0)->and($proceeds->feeCents)->toBe(15_000);

    // Met 30.000 euro betaalde omzet in het venster: 12,5%.
    $seller = User::factory()->verified()->business()->create(['company_id' => $company->id]);
    Order::factory()->create(['seller_id' => $seller->id, 'hammer_cents' => 3_000_000, 'status' => OrderStatus::Completed]);

    expect(PlatformFacts::revenueCentsFor($company->fresh()))->toBe(3_000_000)
        ->and(Money::payoutFor(100_000, $company->fresh())->feePercentage)->toBe(12.5);

    // Eén getal wijzigen werkt overal door.
    config(['aucvy.pricing.fee_tiers' => [['up_to_cents' => null, 'percent' => 5.0]]]);
    expect(Money::payoutFor(100_000, $company->fresh())->feeCents)->toBe(5_000);

    // Zonder staffel: het oude tarief van het pakket.
    config(['aucvy.pricing.use_fee_tiers' => false]);
    expect(Money::payoutFor(100_000, $company->fresh())->feePercentage)->toBe(3.5);
});

it('zet 15 euro administratiekosten op elke order, met btw, ook onder de margeregeling', function () {
    $margin = Money::compute(VatRegime::Margin, 100_000, adminFeeCents: PlatformFacts::adminFeeCents());
    $vat = Money::compute(VatRegime::Vat21, 100_000, adminFeeCents: PlatformFacts::adminFeeCents());

    expect($margin->adminFeeCents)->toBe(1_500)
        ->and($margin->vatCents)->toBe(315)
        ->and($margin->totalCents)->toBe(100_000 + 1_500 + 315)
        ->and($vat->vatCents)->toBe(21_000 + 315)
        ->and(collect($margin->lines())->firstWhere('key', 'admin_fee')->amountCents)->toBe(1_500);

    $order = Order::factory()->create();

    expect($order->fresh()->admin_fee_cents)->toBe(1_500)
        ->and(Money::forOrder($order->lot, $order->hammer_cents, $order->transport_cents)->equals(OrderCosts::fromOrder($order->fresh())))->toBeTrue();

    config(['aucvy.pricing.admin_fee_cents' => 0]);
    expect(Money::forOrder($order->lot, 100_000)->totalCents)->toBe(100_000 + Money::compute($order->lot->vat_regime, 100_000)->vatCents);
});

it('laat tot 250 euro bieden met bevestigd e-mailadres en telefoon, daarboven alleen met identiteit', function () {
    $lot = Lot::factory()->live()->create();
    $auction = Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDay(), 'start_price_cents' => 10_000, 'current_price_cents' => 10_000]);
    $light = User::factory()->create(['email_verified_at' => now(), 'phone' => '+31612345678', 'phone_verified_at' => now()]);

    $bid = app(PlaceBid::class)->handle($auction, $light, 12_000, (string) Str::uuid());
    expect($bid->amount_cents)->toBe(12_000);

    expect(fn () => app(PlaceBid::class)->handle($auction->fresh(), $light, 30_000, (string) Str::uuid()))->toThrow(BidRejected::class);

    $unverifiedPhone = User::factory()->create(['email_verified_at' => now(), 'phone_verified_at' => null]);
    expect(fn () => app(PlaceBid::class)->handle($auction->fresh(), $unverifiedPhone, 13_000, (string) Str::uuid()))->toThrow(BidRejected::class);
});

it('vraagt de borg in treden en accepteert alleen een borg die groot genoeg is', function () {
    expect(PlatformFacts::depositRequiredFor(250_000))->toBe(0)
        ->and(PlatformFacts::depositRequiredFor(250_001))->toBe(20_000)
        ->and(PlatformFacts::depositRequiredFor(1_500_000))->toBe(50_000)
        ->and(PlatformFacts::depositRequiredFor(3_000_000))->toBe(100_000);

    $lot = Lot::factory()->live()->create();
    $auction = Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDay(), 'start_price_cents' => 1_000_000, 'current_price_cents' => 1_000_000]);
    $buyer = User::factory()->verified()->create();

    expect(fn () => app(PlaceBid::class)->handle($auction, $buyer, 1_100_000, (string) Str::uuid()))->toThrow(BidRejected::class);

    Deposit::factory()->create(['auction_id' => $auction->id, 'user_id' => $buyer->id, 'amount_cents' => 20_000, 'status' => DepositStatus::Authorised]);
    expect(fn () => app(PlaceBid::class)->handle($auction->fresh(), $buyer, 1_100_000, (string) Str::uuid()))->toThrow(BidRejected::class);

    Deposit::query()->update(['amount_cents' => 50_000]);
    expect(app(PlaceBid::class)->handle($auction->fresh(), $buyer, 1_100_000, (string) Str::uuid())->amount_cents)->toBe(1_100_000);
});

it('laat de verkoper per kavel marge of btw kiezen', function () {
    $seller = User::factory()->verified()->business()->create();
    $lot = Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id]);

    $this->actingAs($seller)->patchJson("/api/drafts/{$lot->id}", ['vat_regime' => 'vat_21'])
        ->assertOk()
        ->assertJsonPath('draft.vat_regime', 'vat_21');

    expect($lot->fresh()->vat_regime)->toBe(VatRegime::Vat21);

    $this->actingAs($seller)->patchJson("/api/drafts/{$lot->id}", ['vat_regime' => 'nope'])->assertStatus(422);
});

it('toont de gerealiseerde prijs en de biednaam van de koper op een verkochte kavel', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    $order->lot->forceFill(['status' => LotStatus::Sold])->save();

    $this->get(route('lots.show', $order->lot))
        ->assertOk()
        ->assertSee('Verkocht voor '.Money::format($order->hammer_cents))
        ->assertSee($order->buyer->publicName())
        ->assertDontSee($order->buyer->name);

    config(['aucvy.pricing.public_sold_prices' => false]);
    $this->get(route('lots.show', $order->lot))->assertOk()->assertDontSee('Verkocht voor');
});

it('geeft een koper een biednaam als Bieder met een nummer, die hij zelf kan wijzigen', function () {
    $user = User::factory()->create(['name' => 'Bram Klaassen', 'display_name' => null, 'password' => 'wachtwoord']);

    expect($user->publicName())->toMatch('/^Bieder \d{4,5}$/')
        ->and($user->fresh()->bidder_number)->not->toBeNull()
        ->and($user->publicName())->toBe($user->fresh()->publicName());

    $this->actingAs($user)->patch(route('account.profile'), ['name' => 'Bram Klaassen', 'display_name' => 'Stoelenman', 'phone' => $user->phone])->assertRedirect();
    expect($user->fresh()->publicName())->toBe('Stoelenman');

    $this->actingAs($user)->patch(route('account.profile'), ['name' => 'Bram Klaassen', 'display_name' => 'bram@mail.nl', 'phone' => $user->phone])->assertSessionHasErrors('display_name');
});
