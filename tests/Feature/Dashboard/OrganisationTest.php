<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Support\Iban;

/*
| Organisatie-instellingen (briefing sectie 4): bedrijfsgegevens, pakket,
| verificatiestatus en de uitbetaalrekening, met de rollen als poort.
*/

function orgUser(Company $company, CompanyRole $role): User
{
    return User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => $role, 'type' => 'business']);
}

it('toont de organisatie met pakket en verificatie en laat de beheerder gegevens wijzigen', function () {
    $company = Company::factory()->create(['name' => 'Oude Naam BV', 'vat_number' => null]);
    $admin = orgUser($company, CompanyRole::Admin);

    $this->actingAs($admin)->get(route('organisation.show'))
        ->assertOk()
        ->assertSee('Oude Naam BV')
        ->assertSee('Start')
        ->assertSee('7,5%')
        ->assertSee('Nog niet geverifieerd')
        ->assertSee('Nog geen rekening');

    $this->actingAs($admin)
        ->patch(route('organisation.update'), ['name' => 'Nieuwe Naam BV', 'vat_number' => 'nl 1234.56789.b01'])
        ->assertRedirect(route('organisation.show'))
        ->assertSessionHasNoErrors();

    $company->refresh();
    expect($company->name)->toBe('Nieuwe Naam BV')->and($company->vat_number)->toBe('NL123456789B01');
});

it('zet de naam vast na de KvK-aanvraag en houdt redacteuren op kijken', function () {
    $company = Company::factory()->create(['name' => 'Vast BV', 'kyb_requested_at' => now()]);
    $owner = orgUser($company, CompanyRole::Owner);
    $editor = orgUser($company, CompanyRole::Editor);

    $this->actingAs($owner)->patch(route('organisation.update'), ['name' => 'Anders BV'])->assertSessionHasNoErrors();
    expect($company->fresh()->name)->toBe('Vast BV');

    $this->actingAs($editor)->get(route('organisation.show'))->assertOk()->assertSee('Alleen een eigenaar of beheerder');
    $this->actingAs($editor)->patch(route('organisation.update'), ['name' => 'X'])->assertForbidden();
    $this->actingAs($editor)->patch(route('organisation.payout'), ['iban' => 'NL91ABNA0417164300', 'iban_name' => 'X'])->assertForbidden();

    $this->actingAs(User::factory()->verified()->create())->get(route('organisation.show'))->assertNotFound();
});

it('laat alleen de eigenaar een geldig IBAN vastleggen en bewaart enkel de laatste vier cijfers', function () {
    $company = Company::factory()->create();
    $owner = orgUser($company, CompanyRole::Owner);
    $admin = orgUser($company, CompanyRole::Admin);

    expect(Iban::isValid('NL91 ABNA 0417 1643 00'))->toBeTrue()
        ->and(Iban::isValid('NL91ABNA0417164301'))->toBeFalse()
        ->and(Iban::isValid('DE89370400440532013000'))->toBeTrue();

    $this->actingAs($admin)->patch(route('organisation.payout'), ['iban' => 'NL91ABNA0417164300', 'iban_name' => 'Keukenhuis'])->assertForbidden();

    $this->actingAs($owner)
        ->patch(route('organisation.payout'), ['iban' => 'NL91ABNA0417164301', 'iban_name' => 'Keukenhuis'])
        ->assertSessionHasErrors('iban');

    $this->actingAs($owner)
        ->patch(route('organisation.payout'), ['iban' => 'nl91 abna 0417 1643 00', 'iban_name' => 'Keukenhuis Utrecht BV'])
        ->assertRedirect(route('organisation.show'))
        ->assertSessionHasNoErrors();

    $company->refresh();
    expect($company->payout_iban_last4)->toBe('4300')
        ->and($company->payout_iban_name)->toBe('Keukenhuis Utrecht BV')
        ->and($owner->fresh()->requiresSecondFactor())->toBeTrue();

    $this->actingAs($owner)->get(route('organisation.show'))->assertOk()->assertSee('eindigend op')->assertSee('4300');
    $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee(route('organisation.show'));
});
