<?php

declare(strict_types=1);

use App\Enums\DepositStatus;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use App\Orders\Actions\TransitionOrder;
use App\Support\Money\Money;
use Illuminate\Validation\ValidationException;

/*
| De statusmachine uit hfst. 9. TransitionOrder is de enige plek waar een
| order van status verandert; deze tests pinnen vast dat een verboden
| overgang een fout is, dat elke overgang zijn bijwerkingen meesleept en dat
| alles in het auditlog landt.
*/

function transition(Order $order, OrderStatus $to): Order
{
    return app(TransitionOrder::class)->handle($order, $to);
}

it('loopt de gelukkige route af met alle tijdstempels', function () {
    $order = Order::factory()->create();

    $order = transition($order, OrderStatus::Paid);
    expect($order->paid_at)->not->toBeNull();

    $order = transition($order, OrderStatus::Shipped);
    expect($order->shipped_at)->not->toBeNull();

    $order = transition($order, OrderStatus::Delivered);
    expect($order->delivered_at)->not->toBeNull()
        ->and($order->dispute_window_ends_at->greaterThan(now()->addHours(47)))->toBeTrue();

    $order = transition($order, OrderStatus::Completed);
    expect($order->completed_at)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Completed);
});

it('weigert een verboden overgang hard', function () {
    $order = Order::factory()->create();

    // Van wachten-op-betaling direct naar geleverd bestaat niet.
    expect(fn () => transition($order, OrderStatus::Delivered))
        ->toThrow(ValidationException::class);
});

it('is idempotent voor dezelfde status', function () {
    $order = Order::factory()->create();

    transition($order, OrderStatus::Paid);
    transition($order->fresh(), OrderStatus::Paid);

    // Eén payout, niet twee: webhooks mogen dubbel binnenkomen.
    expect($order->fresh()->payout()->count())->toBe(1);
});

it('schrijft elke overgang naar het auditlog', function () {
    $order = Order::factory()->create();

    transition($order, OrderStatus::Paid);

    $log = AuditLog::query()
        ->where('action', 'order.transition')
        ->where('subject_id', $order->id)
        ->sole();

    expect($log->before)->toBe(['status' => 'awaiting_payment'])
        ->and($log->after['status'])->toBe('paid');
});

describe('de payout-lifecycle', function () {
    it('ontstaat bij betaling met het bedrag uit Money', function () {
        $order = Order::factory()->create();

        transition($order, OrderStatus::Paid);

        $payout = $order->fresh()->payout;
        $proceeds = Money::payoutFor($order->hammer_cents, null);

        expect($payout->status)->toBe(PayoutStatus::Scheduled)
            ->and($payout->amount_cents)->toBe($proceeds->payoutCents)
            ->and($payout->fee_cents)->toBe($proceeds->feeCents)
            ->and($payout->amount_cents + $payout->fee_cents)->toBe($order->hammer_cents);
    });

    it('gaat in de houdstand bij levering', function () {
        $order = Order::factory()->create();
        transition($order, OrderStatus::Paid);
        transition($order->fresh(), OrderStatus::Shipped);
        transition($order->fresh(), OrderStatus::Delivered);

        $payout = $order->fresh()->payout;

        expect($payout->status)->toBe(PayoutStatus::Held)
            ->and($payout->hold_until->greaterThan(now()->addHours(47)))->toBeTrue();
    });

    it('vervalt bij annulering en de kavel gaat terug naar de verkoper', function () {
        $lot = Lot::factory()->create(['status' => LotStatus::Sold]);
        $order = Order::factory()->create(['lot_id' => $lot->id]);
        transition($order, OrderStatus::Paid);

        transition($order->fresh(), OrderStatus::Cancelled);

        expect($order->fresh()->payout->status)->toBe(PayoutStatus::Failed)
            ->and($lot->fresh()->status)->toBe(LotStatus::Unsold);
    });
});

it('verrekent de borg van de winnaar bij betaling', function () {
    $buyer = User::factory()->verified()->create();
    $auction = Auction::factory()->create();

    Deposit::create([
        'user_id' => $buyer->id,
        'auction_id' => $auction->id,
        'amount_cents' => 250_000,
        'status' => DepositStatus::Authorised,
    ]);

    $order = Order::factory()->create([
        'auction_id' => $auction->id,
        'buyer_id' => $buyer->id,
    ]);

    transition($order, OrderStatus::Paid);

    expect(Deposit::sole()->status)->toBe(DepositStatus::Released);
});
