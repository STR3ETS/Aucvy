<?php

declare(strict_types=1);

use App\Enums\CompanyPlan;

/*
| De prijzenpagina (briefing sectie 1): alleen wat vaststaat, uit dezelfde
| bronnen als de code die het afdwingt. Geen beloften die het systeem niet
| waarmaakt.
*/

it('toont de succesfee per plan uit CompanyPlan en de borgdrempel uit de config', function () {
    config()->set('aucvy.deposit.threshold_cents', 250_000);

    $response = $this->get('/prijzen')->assertOk();

    foreach (CompanyPlan::cases() as $plan) {
        $response->assertSee(rtrim(rtrim(number_format($plan->defaultFeePercentage(), 1, ',', '.'), '0'), ',').'%');
    }

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
