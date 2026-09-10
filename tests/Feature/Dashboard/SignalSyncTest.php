<?php

declare(strict_types=1);

use App\Dashboard\SignalSync;
use App\Enums\DisputeStatus;
use App\Enums\PayoutStatus;
use App\Models\AiInsight;
use App\Models\Dispute;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Payout;
use App\Models\User;

/*
| De gebeurtenisgedreven dashboardsignalen (hfst. 13.5): deterministisch PHP,
| idempotent, en een opgelost signaal verdwijnt vanzelf. Elke kaart heeft een
| actie - geen kaart zonder knop.
*/

function syncFor(User $user): void
{
    app(SignalSync::class)->sync($user);
}

it('signaleert ontbrekende transportinformatie en ruimt op zodra het klopt', function () {
    $seller = User::factory()->verified()->create();
    $lot = Lot::factory()->live()->create([
        'seller_id' => $seller->id,
        'pickup_only' => false,
        'transport_from_cents' => null,
    ]);

    syncFor($seller);

    $insight = AiInsight::query()->where('type', 'signal.missing_transport')->sole();

    expect($insight->agent->value)->toBe('flow')
        ->and($insight->hasAction())->toBeTrue()
        ->and($insight->action_params)->toEqual(['lot' => $lot->slug]);

    // De verkoper vult het aan: het signaal verdwijnt - niet "dismissed",
    // het klopt gewoon niet meer.
    $lot->update(['transport_from_cents' => 25_000]);
    syncFor($seller);

    expect(AiInsight::query()->where('type', 'signal.missing_transport')->count())->toBe(0);
});

it('laat een weggeklikt signaal weggeklikt bij een nieuwe sync', function () {
    $seller = User::factory()->verified()->create();
    Lot::factory()->live()->create([
        'seller_id' => $seller->id,
        'pickup_only' => false,
        'transport_from_cents' => null,
    ]);

    syncFor($seller);

    $insight = AiInsight::query()->where('type', 'signal.missing_transport')->sole();
    $insight->dismissed_at = now();
    $insight->save();

    syncFor($seller);

    // Idempotent: geen tweede kaart, en de wegklik blijft staan.
    expect(AiInsight::query()->where('type', 'signal.missing_transport')->count())->toBe(1)
        ->and($insight->fresh()->dismissed_at)->not->toBeNull();
});

it('zet een betaalde order klaar als verzendsignaal voor de verkoper', function () {
    $order = Order::factory()->paid()->create();

    syncFor($order->seller);

    $insight = AiInsight::query()->where('type', 'signal.order_to_ship')->sole();

    expect($insight->user_id)->toBe($order->seller_id)
        ->and($insight->agent->value)->toBe('flow')
        ->and($insight->severity->value)->toBe('urgent')
        ->and($insight->action_params)->toEqual(['order' => $order->order_number]);
});

it('herinnert de koper aan een openstaande betaling', function () {
    $order = Order::factory()->create();

    syncFor($order->buyer);

    $insight = AiInsight::query()->where('type', 'signal.order_to_pay')->sole();

    expect($insight->user_id)->toBe($order->buyer_id)
        ->and($insight->agent->value)->toBe('finn');
});

it('telt vrijgegeven uitbetalingen op tot één kaart', function () {
    $seller = User::factory()->verified()->create();

    foreach ([500_000, 750_000] as $cents) {
        Payout::create([
            'order_id' => Order::factory()->completed()->create(['seller_id' => $seller->id])->id,
            'seller_id' => $seller->id,
            'amount_cents' => $cents,
            'status' => PayoutStatus::Released,
        ]);
    }

    syncFor($seller);

    $insight = AiInsight::query()->where('type', 'signal.payout_ready')->sole();

    // € 12.500,00 - één kaart met het totaal, niet twee losse.
    expect($insight->title)->toContain('12.500');
});

it('signaleert een geschil dat op de verkoper wacht', function () {
    $order = Order::factory()->delivered()->create();

    Dispute::create([
        'order_id' => $order->id,
        'reported_by' => $order->buyer_id,
        'reason' => 'damaged',
        'status' => DisputeStatus::Reported,
        'sla_due_at' => now()->addHours(72),
    ]);

    syncFor($order->seller);

    expect(AiInsight::query()->where('type', 'signal.dispute_response')->sole()->severity->value)
        ->toBe('urgent');
});
