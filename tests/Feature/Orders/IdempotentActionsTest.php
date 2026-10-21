<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotMedia;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProviderEvent;
use App\Models\User;
use App\Orders\Actions\ProcessPaymentWebhook;
use App\Orders\Actions\StartPayment;
use App\Orders\Contracts\PaymentProvider;
use App\Orders\Providers\FakePaymentProvider;
use Illuminate\Support\Facades\Queue;

/*
| Dezelfde aanvraag twee keer geeft dezelfde uitkomst (briefing sectie 2):
| publiceren met een Idempotency-Key, betalen, en een webhook die dubbel
| binnenkomt.
*/

// De sluitjob hoort in de agenda, niet direct op de synchrone testqueue.
beforeEach(fn () => Queue::fake());

function publishableDraft(User $seller): Lot
{
    $lot = Lot::factory()->anonymousDraft()->create([
        'seller_id' => $seller->id,
        'title' => 'Compressor Atlas Copco GA11',
        'category_id' => Category::factory()->create()->id,
        'draft_meta' => ['sale_type' => 'auction', 'start_price_cents' => 150_000, 'ownership_declared' => true],
    ]);

    LotMedia::factory()->create(['lot_id' => $lot->id]);

    return $lot;
}

it('publiceert één keer bij twee aanvragen met dezelfde sleutel', function () {
    $seller = User::factory()->verified()->create();
    $lot = publishableDraft($seller);

    $first = $this->actingAs($seller)
        ->withHeader('Idempotency-Key', 'pub-123')
        ->postJson("/api/drafts/{$lot->id}/publish")
        ->assertOk()
        ->json();

    $second = $this->actingAs($seller)
        ->withHeader('Idempotency-Key', 'pub-123')
        ->postJson("/api/drafts/{$lot->id}/publish")
        ->assertOk()
        ->json();

    expect($second)->toBe($first)
        ->and(Auction::query()->where('lot_id', $lot->id)->count())->toBe(1)
        ->and($lot->fresh()->status)->toBe(LotStatus::Live);
});

it('geeft bij een dubbele klik zonder sleutel dezelfde publicatie terug', function () {
    $seller = User::factory()->verified()->create();
    $lot = publishableDraft($seller);

    $first = $this->actingAs($seller)->postJson("/api/drafts/{$lot->id}/publish")->assertOk()->json('url');
    $second = $this->actingAs($seller)->postJson("/api/drafts/{$lot->id}/publish")->assertOk()->json('url');

    expect($second)->toBe($first)
        ->and(Auction::query()->where('lot_id', $lot->id)->count())->toBe(1);
});

it('geeft bij twee keer betalen dezelfde checkout terug en maakt één betaling', function () {
    $order = Order::factory()->create(['status' => OrderStatus::AwaitingPayment]);

    $first = app(StartPayment::class)->handle($order, $order->buyer);
    $second = app(StartPayment::class)->handle($order, $order->buyer);

    expect($second)->toBe($first)
        ->and(Payment::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and(Payment::query()->where('order_id', $order->id)->value('checkout_url'))->toBe($first);
});

it('verwerkt een dubbele webhook maar één keer en bewaart het event', function () {
    $order = Order::factory()->create(['status' => OrderStatus::AwaitingPayment]);
    app(StartPayment::class)->handle($order, $order->buyer);
    $payment = Payment::query()->where('order_id', $order->id)->sole();

    // De nepprovider meldt "betaald" zodra we dat bij hem vastleggen.
    $provider = app(PaymentProvider::class);
    expect($provider)->toBeInstanceOf(FakePaymentProvider::class);
    $provider->pretend($payment->provider_payment_id, PaymentStatus::Paid);

    $webhook = app(ProcessPaymentWebhook::class);
    $webhook->handle($payment->provider_payment_id, ['id' => $payment->provider_payment_id]);
    $webhook->handle($payment->provider_payment_id, ['id' => $payment->provider_payment_id]);

    $events = ProviderEvent::query()->where('provider', $payment->provider)->get();

    expect($events)->toHaveCount(1)
        ->and($events->first()->event_id)->toBe($payment->provider_payment_id.':paid')
        ->and($events->first()->processed_at)->not->toBeNull()
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});
