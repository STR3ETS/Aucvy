<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceAutoBid;
use App\Auctions\Actions\PlaceBid;
use App\Auctions\Exceptions\BidRejected;
use App\Auctions\Support\DutchPriceCalculator;
use App\Enums\DepositStatus;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Lots\PublishLot;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Deposit;
use App\Models\Lot;
use App\Models\LotMedia;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProviderEvent;
use App\Models\User;
use App\Notifications\MoneyNeedsAttention;
use App\Orders\Actions\ProcessPaymentWebhook;
use App\Orders\Actions\StartPayment;
use App\Orders\Actions\TransitionOrder;
use App\Orders\Contracts\PaymentProvider;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
| Regressies uit de kernreview (geld en bieden): een uitbetaling die via een
| geschil toch vrijkomt, webhooks die na een halve run alsnog afmaken,
| betalingen en borgen die een mens moet zien, prijzen die elkaar niet
| tegenspreken, sleutels per gebruiker, en afmijnen nooit boven wat je zag.
*/

beforeEach(function () {
    Queue::fake();
    config(['aucvy.support.email' => 'support@aucvy.test']);
});

function moneyOrder(array $attributes = []): Order
{
    $seller = User::factory()->verified()->create();

    return Order::factory()->create(['seller_id' => $seller->id, 'status' => OrderStatus::AwaitingPayment, ...$attributes]);
}

function moneyCheckout(Order $order): Payment
{
    app(StartPayment::class)->handle($order, $order->buyer);

    return $order->payments()->latest('id')->firstOrFail();
}

it('houdt een uitbetaling die via een geschil afrondt niet eeuwig op gepland', function () {
    $order = moneyOrder();
    $transition = app(TransitionOrder::class);

    $transition->handle($order, OrderStatus::Paid);
    $transition->handle($order->fresh(), OrderStatus::Shipped);
    $transition->handle($order->fresh(), OrderStatus::Disputed);
    $transition->handle($order->fresh(), OrderStatus::Completed, context: ['reason' => 'dispute_rejected']);

    $payout = $order->fresh()->payout;

    expect($payout->status)->toBe(PayoutStatus::Held)
        ->and($payout->hold_until)->not->toBeNull()
        ->and($payout->hold_until->lessThanOrEqualTo(now()))->toBeTrue();
});

it('maakt een webhook alsnog af als een eerdere poging halverwege strandde', function () {
    $order = moneyOrder();
    $payment = moneyCheckout($order);
    app(PaymentProvider::class)->pretend($payment->provider_payment_id, PaymentStatus::Paid);

    // Zo ziet de database eruit als de betaling al vastlag maar de orderovergang mislukte.
    ProviderEvent::query()->create(['provider' => $payment->provider, 'event_id' => $payment->provider_payment_id.':paid', 'type' => 'payment.paid', 'payload' => [], 'received_at' => now(), 'status' => ProviderEvent::STATUS_RECEIVED]);
    $payment->forceFill(['status' => PaymentStatus::Paid, 'paid_at' => now()])->save();

    app(ProcessPaymentWebhook::class)->handle($payment->provider_payment_id);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(ProviderEvent::query()->where('event_id', $payment->provider_payment_id.':paid')->sole()->status)->toBe(ProviderEvent::STATUS_PROCESSED);
});

it('laat de termijnsweep een lopende betaalpoging met rust en meldt geld voor een geannuleerde order aan support', function () {
    Notification::fake();
    $order = moneyOrder(['payment_deadline_at' => now()->subMinute()]);
    $payment = moneyCheckout($order);

    $this->artisan('orders:advance')->assertSuccessful();
    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);

    $payment->forceFill(['created_at' => now()->subMinutes(31)])->save();
    $this->artisan('orders:advance')->assertSuccessful();
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);

    app(PaymentProvider::class)->pretend($payment->provider_payment_id, PaymentStatus::Paid);
    app(ProcessPaymentWebhook::class)->handle($payment->provider_payment_id);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(AuditLog::query()->where('action', 'payment.needs_attention')->exists())->toBeTrue();

    Notification::assertSentOnDemand(MoneyNeedsAttention::class, fn (MoneyNeedsAttention $mail, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'support@aucvy.test' && str_contains($mail->subject, 'geannuleerde bestelling'));
});

it('houdt de borg vast en waarschuwt support als de winnaar niet betaalt', function () {
    Notification::fake();
    $auction = Auction::factory()->create(['closed_at' => now()->subDay()]);
    $buyer = User::factory()->verified()->create();
    $order = moneyOrder(['auction_id' => $auction->id, 'lot_id' => $auction->lot_id, 'buyer_id' => $buyer->id, 'payment_deadline_at' => now()->subMinute()]);
    $deposit = Deposit::create(['user_id' => $buyer->id, 'auction_id' => $auction->id, 'amount_cents' => 250_000, 'status' => DepositStatus::Authorised]);

    $this->artisan('orders:advance')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($deposit->fresh()->status)->toBe(DepositStatus::Authorised)
        ->and(AuditLog::query()->where('action', 'deposit.capture_pending')->exists())->toBeTrue();

    Notification::assertSentOnDemand(MoneyNeedsAttention::class, fn (MoneyNeedsAttention $mail) => str_contains($mail->subject, 'Borg innen'));
});

it('weigert prijzen die elkaar tegenspreken bij het publiceren', function () {
    $seller = User::factory()->verified()->create();
    $category = Category::factory()->create();

    $draft = function (array $meta) use ($seller, $category): Lot {
        $lot = Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id, 'status' => LotStatus::Draft, 'title' => 'Prijzen', 'slug' => null, 'category_id' => $category->id, 'draft_meta' => ['ownership_declared' => true, ...$meta]]);
        LotMedia::factory()->create(['lot_id' => $lot->id]);

        return $lot;
    };

    try {
        app(PublishLot::class)->handle($draft(['sale_type' => 'auction', 'start_price_cents' => 50_000, 'reserve_price_cents' => 100_000, 'buy_now_price_cents' => 80_000]), $seller);
        $this->fail('Koop-direct onder de reserve had geweigerd moeten worden.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('buy_now_price_cents');
    }

    try {
        app(PublishLot::class)->handle($draft(['sale_type' => 'dutch', 'dutch_start_cents' => 100_000, 'dutch_floor_cents' => 40_000, 'dutch_step_cents' => 5_000, 'dutch_interval_s' => 3600, 'reserve_price_cents' => 60_000]), $seller);
        $this->fail('Een bodem onder de reserve had geweigerd moeten worden.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('dutch');
    }
});

it('geeft een hergebruikte sleutel van een ander nooit andermans bod terug', function () {
    $auction = Auction::factory()->create(['start_price_cents' => 100_000, 'current_price_cents' => 100_000]);
    $anna = User::factory()->verified()->create();
    $bram = User::factory()->verified()->create();
    $key = (string) Str::uuid();

    $bid = app(PlaceBid::class)->handle($auction, $anna, 100_000, $key);

    expect(fn () => app(PlaceBid::class)->handle($auction, $bram, 110_000, $key))->toThrow(ValidationException::class);
    expect($auction->fresh()->current_price_cents)->toBe(100_000)
        ->and($bid->fresh()->user_id)->toBe($anna->id);
});

it('mijnt nooit af boven de prijs die de koper zag', function () {
    $auction = Auction::factory()->make(['starts_at' => now()->subHours(2), 'dutch_start_cents' => 100_000, 'dutch_floor_cents' => 50_000, 'dutch_step_cents' => 5_000, 'dutch_interval_s' => 3600]);
    $calculator = app(DutchPriceCalculator::class);
    $actual = $calculator->priceAt($auction);

    expect($calculator->isWithinTolerance($auction, $actual))->toBeTrue()
        ->and($calculator->isWithinTolerance($auction, $actual + 5_000))->toBeTrue()
        ->and($calculator->isWithinTolerance($auction, $actual - 5_000))->toBeFalse();
});

it('schrijft een oude betaalpoging af als er een nieuwe start en laat een leider geen plafond onder zijn bod zetten', function () {
    $order = moneyOrder();
    $first = moneyCheckout($order);

    $this->travelTo(now()->addMinutes(31));
    $second = moneyCheckout($order);

    expect($second->id)->not->toBe($first->id)
        ->and($first->fresh()->status)->toBe(PaymentStatus::Expired)
        ->and($second->status)->toBe(PaymentStatus::Pending);

    $auction = Auction::factory()->create(['start_price_cents' => 100_000, 'current_price_cents' => 100_000]);
    $anna = User::factory()->verified()->create();
    app(PlaceBid::class)->handle($auction, $anna, 120_000, (string) Str::uuid());

    expect(fn () => app(PlaceAutoBid::class)->handle($auction->fresh(), $anna, 110_000))->toThrow(BidRejected::class);
});
