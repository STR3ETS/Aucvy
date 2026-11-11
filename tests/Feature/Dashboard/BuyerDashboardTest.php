<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceBid;
use App\Dashboard\ActivityFeed;
use App\Enums\LotStatus;
use App\Ivy\Chat\Team;
use App\Models\AiInsight;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;

/*
| Het dashboard vanuit de koper: overboden zijn is hét signaal dat aandacht
| vraagt, en wie alleen een concept in de la heeft is nog geen verkoper.
*/

it('toont een aandachtspunt als je overboden bent', function () {
    $anna = User::factory()->verified()->create();
    $bram = User::factory()->verified()->create();

    $lot = Lot::factory()->live()->create(['title' => 'Minigraafmachine 3,5 ton']);
    $auction = Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);

    app(PlaceBid::class)->handle($auction, $anna, 100_000, 'test:1');
    app(PlaceBid::class)->handle($auction->fresh(), $bram, 105_000, 'test:2');

    // Op het overzicht: de kavel bij de lopende biedingen, met het label, en een teamlid waar iets wacht.
    $this->actingAs($anna)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Overboden')
        ->assertSee('Minigraafmachine 3,5 ton')
        ->assertSee('wacht op je');

    // Het aandachtspunt zelf staat als taak bij het teamlid in het paneel.
    $titles = app(Team::class)->open($anna)->pluck('title');

    expect($titles->contains(fn (string $title): bool => str_contains($title, 'Je bent overboden op') && str_contains($title, 'Minigraafmachine 3,5 ton')))->toBeTrue();
});

it('laat het signaal verdwijnen zodra je weer bovenaan staat', function () {
    $anna = User::factory()->verified()->create();
    $bram = User::factory()->verified()->create();

    $lot = Lot::factory()->live()->create();
    $auction = Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);

    app(PlaceBid::class)->handle($auction, $anna, 100_000, 'test:1');
    app(PlaceBid::class)->handle($auction->fresh(), $bram, 105_000, 'test:2');
    $this->actingAs($anna)->get('/dashboard')->assertSee('Overboden');

    expect(app(Team::class)->open($anna)->pluck('title')->contains(fn (string $title): bool => str_contains($title, 'Je bent overboden op')))->toBeTrue();

    app(PlaceBid::class)->handle($auction->fresh(), $anna, 110_000, 'test:3');

    // De taak is weg; de melding van toen blijft als historie in de lijst staan.
    $this->actingAs($anna)->get('/dashboard')->assertOk();

    $tasks = app(ActivityFeed::class)
        ->for($anna->fresh(), AiInsight::query()->where('user_id', $anna->id)->current()->get())['items'];

    expect(collect($tasks)->where('kind', 'task')->filter(fn (array $item): bool => str_contains($item['title'], 'overboden')))->toBeEmpty();
});

it('opent voor iemand met alleen een concept in kopersmodus', function () {
    $user = User::factory()->verified()->create();
    Lot::factory()->create(['seller_id' => $user->id, 'status' => LotStatus::Draft]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Koperintentie');
});

it('toont een lopend bod als kaart met een ring en het bedrag om weer op kop te komen', function () {
    $anna = User::factory()->verified()->create();
    $bram = User::factory()->verified()->create();

    $lot = Lot::factory()->live()->create(['title' => 'Vorkheftruck Linde E16']);
    $auction = Auction::factory()->create(['lot_id' => $lot->id, 'start_price_cents' => 100_000, 'current_price_cents' => 100_000]);

    app(PlaceBid::class)->handle($auction, $anna, 100_000, 'test:1');
    app(PlaceBid::class)->handle($auction->fresh(), $bram, 105_000, 'test:2');

    $this->actingAs($anna)
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeInOrder(['Lopende biedingen', 'Vorkheftruck Linde E16', 'Overboden', 'Huidig bod', 'Jouw bod', 'Weer op kop vanaf', 'Bied opnieuw'])
        ->assertSee('class="d-ring is-', escape: false);
});
