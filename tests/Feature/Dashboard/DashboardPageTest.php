<?php

declare(strict_types=1);

use App\Dashboard\DemandForecast;
use App\Dashboard\IntentFunnel;
use App\Enums\OrderStatus;
use App\Models\AiInsight;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use App\Models\WatchlistItem;
use App\Orders\Actions\TransitionOrder;

/*
| Het dashboard leest; het rekent deterministisch en doet geen AI-calls
| (hfst. 13.5). Volgorde en inhoud van de blokken zijn het ontwerp.
*/

it('toont een verkoper zijn werkplan met signalen en teamchips', function () {
    $seller = User::factory()->verified()->create();
    Lot::factory()->live()->create([
        'seller_id' => $seller->id,
        'pickup_only' => false,
        'transport_from_cents' => null,
    ]);

    $this->actingAs($seller)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Jouw AI-team')
        ->assertSee('Vraagt vandaag je aandacht')
        ->assertSee('Transportinformatie ontbreekt')
        ->assertSeeInOrder(['Flow', 'Bekijken']);
});

it('toont een koper zijn eigen signalen via de toggle', function () {
    $order = Order::factory()->create();

    $this->actingAs($order->buyer)
        ->get(route('dashboard', ['rol' => 'koper']))
        ->assertOk()
        ->assertSee('Betaal');
});

it('verwelkomt een nieuwe gebruiker met startstappen in plaats van lege blokken', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Welkom bij Aucvy')
        ->assertSee('Drie stappen');
});

it('laat een signaal wegklikken en houdt dat vast', function () {
    $seller = User::factory()->verified()->create();
    Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id]);

    $this->actingAs($seller)->get(route('dashboard'));

    $insight = AiInsight::query()->where('type', 'signal.unfinished_draft')->sole();

    $this->actingAs($seller)
        ->postJson("/api/insights/{$insight->id}/dismiss")
        ->assertOk();

    expect($insight->fresh()->dismissed_at)->not->toBeNull();
});

it('verbergt andermans signalen ook voor het wegklikken', function () {
    $seller = User::factory()->verified()->create();
    Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id]);
    $this->actingAs($seller)->get(route('dashboard'));

    $insight = AiInsight::query()->where('type', 'signal.unfinished_draft')->sole();

    $this->actingAs(User::factory()->create())
        ->postJson("/api/insights/{$insight->id}/dismiss")
        ->assertNotFound();
});

describe('de cijferblokken', function () {
    it('telt de intentietrechter zonder persoonsgegevens', function () {
        $seller = User::factory()->verified()->create();
        $lot = Lot::factory()->live()->create(['seller_id' => $seller->id]);
        $auction = Auction::factory()->create(['lot_id' => $lot->id]);

        $business = User::factory()->verified()->business()->create();
        WatchlistItem::create(['user_id' => $business->id, 'lot_id' => $lot->id]);
        Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $business->id]);

        $intent = app(IntentFunnel::class)->forSeller($seller);

        expect($intent)->toEqual([
            'watchers' => 1,
            'viewings' => 0,
            'bidders' => 1,
            'business' => 1,
        ]);
    });

    it('houdt de voorspelling een bandbreedte, nooit een punt', function () {
        $seller = User::factory()->verified()->create();

        $order = Order::factory()->create(['seller_id' => $seller->id]);
        app(TransitionOrder::class)->handle($order, OrderStatus::Paid);

        $forecast = app(DemandForecast::class)->forSeller($seller);
        $predicted = collect($forecast['weeks'])->reject(fn (array $week) => $week['measured']);

        expect($forecast['has_history'])->toBeTrue()
            ->and(count($forecast['weeks']))->toBe(11)
            ->and($predicted)->toHaveCount(3)
            ->and($predicted->every(fn (array $week): bool => $week['high_cents'] > $week['low_cents']))->toBeTrue();
    });

    it('is eerlijk zonder historie', function () {
        $seller = User::factory()->verified()->create();

        expect(app(DemandForecast::class)->forSeller($seller)['has_history'])->toBeFalse();
    });
});
