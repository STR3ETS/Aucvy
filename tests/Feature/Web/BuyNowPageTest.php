<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Models\Auction;
use App\Models\Lot;

/*
| Direct kopen als eigen overzicht (briefing sectie 1): dezelfde zoekpagina,
| vooraf gefilterd op de verkoopvorm, met een eigen kop en URL.
*/

it('toont alleen kavels met koop direct', function () {
    $buyNow = Lot::factory()->create(['status' => LotStatus::Live, 'sale_type' => SaleType::BuyNow, 'title' => 'Palletwagen direct']);
    Auction::factory()->create(['lot_id' => $buyNow->id, 'buy_now_price_cents' => 50_000, 'buy_now_active' => true]);

    $auction = Lot::factory()->create(['status' => LotStatus::Live, 'sale_type' => SaleType::Auction, 'title' => 'Heftruck op veiling']);
    Auction::factory()->create(['lot_id' => $auction->id]);

    $this->get('/direct-kopen')
        ->assertOk()
        ->assertSee('Direct kopen')
        ->assertSee('Palletwagen direct')
        ->assertDontSee('Heftruck op veiling');
});

it('heeft een eigen kop met de betaaltermijn uit de instellingen', function () {
    config()->set('aucvy.orders.payment_deadline_hours', 36);

    $this->get('/direct-kopen')
        ->assertOk()
        ->assertSee('Geen biedstrijd.')
        ->assertSee('Zo werkt koop direct')
        ->assertSee('Binnen 36 u')
        ->assertSee('Slim bieden.')
        // De banden van de veilingenpagina horen hier niet.
        ->assertDontSee('een getal erachter.');
});

it('staat in de sitemap', function () {
    $this->get('/sitemap.xml')->assertOk()->assertSee(route('lots.buy-now'), escape: false);
});
