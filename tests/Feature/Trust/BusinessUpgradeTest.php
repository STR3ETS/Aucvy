<?php

declare(strict_types=1);

use App\Compliance\DenialReason;
use App\Enums\CompanyRole;
use App\Enums\UserType;
use App\Models\User;

/*
| Eén account voor kopen en verkopen: een particulier koopt, een zakelijk
| account koopt én verkoopt. Wie later wil verkopen, maakt zijn eigen account
| zakelijk; er komt geen tweede account (besluit Raph, 2 november 2026).
*/

it('laat een particulier kopen maar niet verkopen, en toont hem de weg naar zakelijk', function () {
    $buyer = User::factory()->verified()->create(['type' => UserType::Private, 'company_id' => null]);

    expect($buyer->isBusiness())->toBeFalse();

    // Geen verkoopmenu's, geen wissel naar verkoper; wel de knop om zakelijk te worden.
    $this->actingAs($buyer)->get(route('dashboard', ['rol' => 'verkoper']))
        ->assertOk()
        ->assertDontSee('Als verkoper')
        ->assertDontSee(route('dashboard.sales'), false)
        ->assertDontSee(route('dashboard.payouts'), false)
        ->assertSee(route('account.business'), false)
        ->assertSee('Zakelijk verkopen');

    // Wie toch in Kavel plaatsen komt, ziet het meteen.
    $this->actingAs($buyer)->get(route('sell'))
        ->assertOk()
        ->assertSee('Verkopen kan alleen met een zakelijk account.');

    expect(DenialReason::BusinessAccountRequired->resolutionRoute())->toBe('account.business');
});

it('maakt van een particulier een zakelijk account, met hetzelfde account', function () {
    $buyer = User::factory()->verified()->create(['type' => UserType::Private, 'company_id' => null, 'email' => 'koper@example.test']);

    $this->actingAs($buyer)->get(route('account.business'))->assertOk()->assertSee('Maak mijn account zakelijk');

    $this->actingAs($buyer)->post(route('account.business.store'), ['company_name' => 'Bakker Horeca B.V.', 'kvk_number' => '12345678'])
        ->assertRedirect(route('verification.overview'));

    $buyer->refresh();

    expect($buyer->isBusiness())->toBeTrue()
        ->and($buyer->email)->toBe('koper@example.test')
        ->and($buyer->company->name)->toBe('Bakker Horeca B.V.')
        ->and($buyer->company->kvk_number)->toBe('12345678')
        ->and($buyer->company_role)->toBe(CompanyRole::Owner)
        ->and(User::query()->where('email', 'koper@example.test')->count())->toBe(1);

    // Nu wel: de verkoopmenu's en Kavel plaatsen.
    $this->actingAs($buyer)->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('dashboard.sales'), false)
        ->assertSee('Kavel plaatsen');
});

it('stuurt een zakelijk account dat al zakelijk is door naar de verificatie', function () {
    $seller = User::factory()->verified()->business()->create();

    $this->actingAs($seller)->get(route('account.business'))->assertRedirect(route('verification.overview'));
});
