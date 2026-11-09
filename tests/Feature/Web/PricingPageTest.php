<?php

declare(strict_types=1);

use App\Enums\CompanyPlan;

/*
| De prijzenpagina (briefing sectie 1): alleen wat vaststaat, uit dezelfde
| bronnen als de code die het afdwingt. Geen beloften die het systeem niet
| waarmaakt.
*/

it('toont de succesfee uit de staffel en de borgdrempel uit de config', function () {
    config()->set('aucvy.deposit.threshold_cents', 250_000);
    config()->set('aucvy.pricing.use_fee_tiers', true);
    config()->set('aucvy.pricing.fee_tiers', [
        ['up_to_cents' => 2_500_000, 'percent' => 15],
        ['up_to_cents' => 25_000_000, 'percent' => 10],
        ['up_to_cents' => null, 'percent' => 6],
    ]);

    // Dezelfde staffel als de afrekening: het begintarief, het laagste tarief en de drempels.
    $response = $this->get('/prijzen')
        ->assertOk()
        ->assertSee('15%')
        ->assertSee('10%')
        ->assertSee('6%')
        ->assertSee('Tot € 25.000')
        ->assertSee('Bekijk de hele staffel')
        ->assertSee('administratiekosten');

    $response
        ->assertSee('€ 2.500')
        ->assertSee('Geen opgeld')
        ->assertSee('nog niet vastgesteld')
        ->assertDontSee('escrow')
        ->assertDontSee('gegarandeerd');
});

it('staat in de navigatie en de sitemap', function () {
    $this->get('/')->assertOk()->assertSee(route('pricing'), escape: false);
    $this->get('/sitemap.xml')->assertOk()->assertSee(route('pricing'), escape: false);
});

it('valt terug op het tarief per plan als de staffel uit staat', function () {
    config()->set('aucvy.pricing.use_fee_tiers', false);

    $response = $this->get('/prijzen')->assertOk()->assertDontSee('Bekijk de hele staffel');

    foreach (CompanyPlan::cases() as $plan) {
        $response->assertSee(rtrim(rtrim(number_format($plan->defaultFeePercentage(), 1, ',', '.'), '0'), ',').'%');
    }
});
