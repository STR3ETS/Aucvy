<?php

declare(strict_types=1);

use App\Enums\DepositStatus;
use App\Models\Auction;
use App\Models\Deposit;
use App\Models\Lot;
use App\Models\User;

/*
| De borg vóór het bieden (hfst. 9): boven de drempel eerst reserveren, en de
| kavelpagina zegt dat vooraf. Deze tests dekken de route van "borg regelen"
| tot een bod dat er daarna wél doorheen mag.
*/

function depositAuction(): Auction
{
    $lot = Lot::factory()->live()->create();

    return Auction::factory()->create([
        'lot_id' => $lot->id,
        // Start boven de borgdrempel: elk bod vraagt een reservering.
        'start_price_cents' => 1_000_000,
        'current_price_cents' => 1_000_000,
    ]);
}

beforeEach(function () {
    $this->bidder = User::factory()->verified()->create();
});

it('vertelt de kijker vooraf of zijn borg gedekt is', function () {
    $auction = depositAuction();

    $state = $this->actingAs($this->bidder)
        ->getJson("/api/auctions/{$auction->id}/state")
        ->assertOk();

    expect($state->json('viewer.deposit.covered'))->toBeFalse()
        ->and($state->json('viewer.deposit.threshold_cents'))->toBe((int) config('aucvy.deposit.threshold_cents'));
});

it('weigert een bod boven de drempel zonder borg', function () {
    $auction = depositAuction();

    $this->actingAs($this->bidder)
        ->postJson("/api/auctions/{$auction->id}/bids", ['amount_cents' => 1_000_000], [
            'Idempotency-Key' => 'borg-test-1',
        ])
        ->assertStatus(403)
        ->assertJsonPath('reason', 'deposit_required');
});

it('reserveert de borg via de nepcheckout en dan mag het bod erdoor', function () {
    $auction = depositAuction();

    // Stap 1: borg aanvragen → checkout-url.
    $response = $this->actingAs($this->bidder)
        ->postJson("/api/auctions/{$auction->id}/deposit")
        ->assertOk();

    $deposit = Deposit::sole();

    expect($response->json('checkout_url'))->toContain('/dev/borg/')
        ->and($deposit->status)->toBe(DepositStatus::Pending)
        ->and($deposit->amount_cents)->toBe((int) config('aucvy.deposit.threshold_cents'));

    // Stap 2: de nepreservering bevestigen.
    $this->actingAs($this->bidder)
        ->post(route('dev.deposit.store', $deposit))
        ->assertRedirect(route('lots.show', $auction->lot));

    expect($deposit->fresh()->status)->toBe(DepositStatus::Authorised);

    // Stap 3: de stand zegt gedekt, en het bod mag er nu doorheen.
    $this->actingAs($this->bidder)
        ->getJson("/api/auctions/{$auction->id}/state")
        ->assertJsonPath('viewer.deposit.covered', true);

    $this->actingAs($this->bidder)
        ->postJson("/api/auctions/{$auction->id}/bids", ['amount_cents' => 1_000_000], [
            'Idempotency-Key' => 'borg-test-2',
        ])
        ->assertCreated();
});

it('hergebruikt een lopende aanvraag in plaats van te dupliceren', function () {
    $auction = depositAuction();

    $this->actingAs($this->bidder)->postJson("/api/auctions/{$auction->id}/deposit");
    $this->actingAs($this->bidder)->postJson("/api/auctions/{$auction->id}/deposit");

    expect(Deposit::count())->toBe(1);
});

it('meldt een al gedekte borg zonder nieuwe checkout', function () {
    $auction = depositAuction();

    Deposit::create([
        'user_id' => $this->bidder->id,
        'auction_id' => $auction->id,
        'amount_cents' => 250_000,
        'status' => DepositStatus::Authorised,
    ]);

    $this->actingAs($this->bidder)
        ->postJson("/api/auctions/{$auction->id}/deposit")
        ->assertOk()
        ->assertJsonPath('covered', true);
});

it('vraagt een account', function () {
    $auction = depositAuction();

    $this->postJson("/api/auctions/{$auction->id}/deposit")->assertStatus(401);
});
