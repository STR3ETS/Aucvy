<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceBid;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\OutbidPush;
use Illuminate\Support\Facades\Notification;

/*
| Web push voor "je bent overboden" (hfst. 14): mail is daarvoor te laat.
| Alleen wie een apparaat heeft ingeschreven krijgt een push - en de
| inschrijving zelf hangt aan het account.
*/

beforeEach(function () {
    Notification::fake();

    $lot = Lot::factory()->live()->create();
    $this->auction = Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);

    $this->anna = User::factory()->verified()->create();
    $this->bram = User::factory()->verified()->create();
});

it('pusht naar de overboden bieder met een ingeschreven apparaat', function () {
    $this->anna->updatePushSubscription('https://push.example/anna', 'p256dh-sleutel', 'auth-token', 'aes128gcm');

    app(PlaceBid::class)->handle($this->auction, $this->anna, 100_000, 'test:1');
    app(PlaceBid::class)->handle($this->auction->fresh(), $this->bram, 105_000, 'test:2');

    Notification::assertSentTo($this->anna, OutbidPush::class, function (OutbidPush $notification) {
        $message = $notification->toWebPush($this->anna)->toArray();

        return $message['title'] === 'Je bent overboden'
            && str_contains($message['body'], '1.050,00');
    });
});

it('pusht niet naar wie geen apparaat heeft ingeschreven', function () {
    app(PlaceBid::class)->handle($this->auction, $this->anna, 100_000, 'test:1');
    app(PlaceBid::class)->handle($this->auction->fresh(), $this->bram, 105_000, 'test:2');

    Notification::assertNotSentTo($this->anna, OutbidPush::class);
});

it('schrijft een apparaat in en uit via de api', function () {
    $subscription = [
        'endpoint' => 'https://push.example/apparaat-1',
        'keys' => ['p256dh' => 'sleutel', 'auth' => 'token'],
    ];

    $this->actingAs($this->anna)
        ->postJson('/api/push/subscription', $subscription)
        ->assertCreated();

    expect($this->anna->pushSubscriptions()->count())->toBe(1);

    $this->actingAs($this->anna)
        ->deleteJson('/api/push/subscription', ['endpoint' => $subscription['endpoint']])
        ->assertOk();

    expect($this->anna->fresh()->pushSubscriptions()->count())->toBe(0);
});

it('vraagt om inloggen voor een inschrijving', function () {
    $this->postJson('/api/push/subscription', [
        'endpoint' => 'https://push.example/x',
        'keys' => ['p256dh' => 'a', 'auth' => 'b'],
    ])->assertUnauthorized();
});
