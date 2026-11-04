<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceBid;
use App\Lots\Interest\Interest;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotSignal;
use App\Models\User;

/*
| Interesse en Hot (hoofdbriefing p. 7): alleen gemeten aantallen, de
| verkoper telt niet mee, en Hot alleen bij de hoogste 10% van de
| categorie met genoeg kijkers en bieders. Kopers zien nooit "Rustig".
*/

function lotForInterest(?Category $category = null): Lot
{
    $seller = User::factory()->verified()->business()->create();
    $lot = Lot::factory()->live()->create(['seller_id' => $seller->id, 'company_id' => $seller->company_id] + ($category ? ['category_id' => $category->id] : []));
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDays(2)]);

    return $lot->fresh(['auction', 'seller']);
}

it('telt een kijker één keer per dag, de verkoper niet, en weergaven elke keer', function () {
    $lot = lotForInterest();

    $this->get(route('lots.show', $lot))->assertOk();
    $this->get(route('lots.show', $lot))->assertOk();

    expect(LotSignal::query()->where('lot_id', $lot->id)->where('kind', LotSignal::VIEWER)->count())->toBe(1)
        ->and(LotSignal::query()->where('lot_id', $lot->id)->where('kind', LotSignal::VIEW)->count())->toBe(2);

    $this->actingAs($lot->seller)->get(route('lots.show', $lot))->assertOk();
    expect(LotSignal::query()->where('lot_id', $lot->id)->where('kind', LotSignal::VIEWER)->count())->toBe(1);

    $buyer = User::factory()->verified()->create();
    $this->actingAs($buyer)->get(route('lots.show', $lot).'?utm_source=instagram&utm_medium=social&utm_campaign=kavel-'.$lot->public_id)->assertOk();
    $this->actingAs($buyer)->postJson('/api/lots/'.$lot->id.'/watch')->assertCreated();
    $this->actingAs($buyer)->postJson(route('lots.share', $lot), ['channel' => 'link'])->assertCreated();

    $stats = Interest::forSeller($lot);
    expect($stats['viewers'])->toBe(2)
        ->and($stats['saved'])->toBe(1)
        ->and($stats['shared'])->toBe(1)
        ->and($stats['sources']['instagram'] ?? 0)->toBe(1)
        ->and(Interest::score($lot))->toBe(1 + 1 + 3 + 3);

    $this->actingAs($lot->seller)->get(route('dashboard.lots.show', $lot))->assertOk()->assertSee('Herkomst van kijkers')->assertSee('Instagram');
});

it('toont kopers de tellers en Hot alleen als de regel daaraan voldoet', function () {
    config(['aucvy.interest.min_viewers' => 3, 'aucvy.interest.min_bidders' => 2]);
    $category = Category::factory()->create();
    $hot = lotForInterest($category);
    $quiet = lotForInterest($category);

    foreach (range(1, 4) as $i) {
        Interest::record($hot, LotSignal::VIEWER, "bezoeker-{$i}");
    }
    Interest::record($quiet, LotSignal::VIEWER, 'bezoeker-1');

    foreach (range(1, 2) as $i) {
        $bidder = User::factory()->verified()->create();
        app(PlaceBid::class)->handle($hot->auction->fresh(), $bidder, $hot->auction->fresh()->current_price_cents + 100_00 * $i, "interesse-{$i}");
    }

    $this->artisan('aucvy:hot-score')->assertSuccessful();

    expect($hot->fresh()->is_hot)->toBeTrue()
        ->and($quiet->fresh()->is_hot)->toBeFalse()
        ->and($hot->fresh()->interest_score)->toBeGreaterThan($quiet->fresh()->interest_score);

    $this->get(route('lots.show', $hot))->assertOk()->assertSee('Veel interesse in deze kavel')->assertSee('Bieders')->assertSee('Hoe meten we dit?');
    $this->get(route('lots.show', $quiet))->assertOk()->assertSee('Interesse in deze kavel')->assertDontSee('Rustig');
    $this->get(route('lots.index'))->assertOk()->assertSee('Hot');
});

it('laat Hot vallen als er te weinig kijkers zijn, ook bij de hoogste score', function () {
    config(['aucvy.interest.min_viewers' => 50]);
    $lot = lotForInterest();
    Interest::record($lot, LotSignal::VIEWER, 'een');
    $lot->forceFill(['is_hot' => true, 'hot_since' => now()])->save();

    $this->artisan('aucvy:hot-score')->assertSuccessful();

    expect($lot->fresh()->is_hot)->toBeFalse()->and($lot->fresh()->hot_since)->toBeNull();
});
