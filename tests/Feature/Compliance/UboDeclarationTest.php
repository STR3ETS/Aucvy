<?php

declare(strict_types=1);

use App\Compliance\Actions\DecideKyb;
use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/*
| UBO-verklaring (briefing sectie 2: KYB met KvK, UBO en IBAN): het bedrijf
| verklaart wie de uiteindelijk belanghebbenden zijn, compliance ziet het
| bij de KvK-beoordeling en bevestigt het mee bij goedkeuring.
*/

function businessOwner(Company $company): User
{
    return User::factory()->create(['type' => 'business', 'company_id' => $company->id, 'company_role' => CompanyRole::Owner, 'email_verified_at' => now()]);
}

it('laat de eigenaar de UBO-verklaring invullen en toont hem in de checklist', function () {
    $company = Company::factory()->create(['verified_at' => null]);
    $owner = businessOwner($company);

    $this->actingAs($owner)->get(route('verification.overview'))->assertOk()->assertSee('UBO-verklaring')->assertSee('Nog niet verklaard');

    $this->actingAs($owner)
        ->post(route('verification.ubo'), ['ubos' => [
            ['name' => 'Sander Vermeulen', 'birth_year' => 1981, 'role' => 'ubo', 'share' => 60],
            ['name' => 'Fleur de Groot', 'birth_year' => 1985, 'role' => 'ubo', 'share' => 40],
        ]])
        ->assertRedirect(route('verification.overview'))
        ->assertSessionHasNoErrors();

    $company->refresh();

    expect($company->ubo_declared_at)->not->toBeNull()
        ->and($company->ubo_declaration)->toHaveCount(2)
        ->and($company->ubo_declaration[0]['name'])->toBe('Sander Vermeulen')
        ->and($company->ubo_declaration[0]['share'])->toBe(60);

    $this->actingAs($owner)->get(route('verification.overview'))->assertOk()->assertSee('Sander Vermeulen')->assertSee('60%');

    // Een bestuurder zonder 25%: pseudo-UBO, geen aandeel nodig.
    $this->actingAs($owner)
        ->post(route('verification.ubo'), ['ubos' => [['name' => 'Bestuurder Jansen', 'birth_year' => 1970, 'role' => 'director']]])
        ->assertSessionHasNoErrors();

    expect($company->fresh()->ubo_declaration[0]['role'])->toBe('director');
});

it('weigert een lege of onvolledige verklaring en houdt redacteuren en particulieren buiten', function () {
    $company = Company::factory()->create(['verified_at' => null]);
    $owner = businessOwner($company);

    $this->actingAs($owner)->from(route('verification.overview'))
        ->post(route('verification.ubo'), ['ubos' => []])
        ->assertSessionHasErrors('ubos');

    $this->actingAs($owner)->from(route('verification.overview'))
        ->post(route('verification.ubo'), ['ubos' => [['name' => 'X', 'birth_year' => 2020, 'role' => 'ubo', 'share' => 10]]])
        ->assertSessionHasErrors(['ubos.0.birth_year', 'ubos.0.share']);

    $editor = User::factory()->create(['type' => 'business', 'company_id' => $company->id, 'company_role' => CompanyRole::Editor, 'email_verified_at' => now()]);
    $this->actingAs($editor)->post(route('verification.ubo'), ['ubos' => [['name' => 'Iemand', 'birth_year' => 1980, 'role' => 'director']]])->assertForbidden();

    $private = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($private)->from(route('verification.overview'))->post(route('verification.ubo'), ['ubos' => [['name' => 'Iemand', 'birth_year' => 1980, 'role' => 'director']]])->assertSessionHasErrors('ubos');
});

it('toont de verklaring in de compliance-queue en bevestigt de UBO bij goedkeuring', function () {
    Notification::fake();

    $company = Company::factory()->create(['verified_at' => null, 'kyb_requested_at' => now(), 'ubo_declaration' => [['name' => 'Sander Vermeulen', 'birth_year' => 1981, 'role' => 'ubo', 'share' => 100]], 'ubo_declared_at' => now()]);
    businessOwner($company);
    $undeclared = Company::factory()->create(['name' => 'Zonder Verklaring BV', 'verified_at' => null, 'kyb_requested_at' => now()]);
    businessOwner($undeclared);
    $staff = User::factory()->create(['is_staff' => true]);

    $this->actingAs($staff)->get(route('admin.compliance'))
        ->assertOk()
        ->assertSee('Sander Vermeulen')
        ->assertSee('100%')
        ->assertSee('Geen UBO-verklaring');

    app(DecideKyb::class)->handle($company, $staff, true);
    app(DecideKyb::class)->handle($undeclared, $staff, true);

    expect($company->fresh()->ubo_verified_at)->not->toBeNull()
        ->and($undeclared->fresh()->ubo_verified_at)->toBeNull();
});
