<?php

declare(strict_types=1);

use App\Enums\UserType;
use App\Lots\DraftAccess;
use App\Models\Company;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

/*
| Registreren (hfst. 13.6): meteen bruikbaar om rond te kijken, bieden pas na
| verificatie. Zakelijk krijgt direct zijn bedrijf eronder.
*/

it('registreert een particulier en stuurt de bevestigingsmail', function () {
    Notification::fake();

    $this->post('/registreren', [
        'terms' => '1',
        'name' => 'Pieter Jansen',
        'email' => 'pieter@example.nl',
        'password' => 'geheim-wachtwoord',
        'password_confirmation' => 'geheim-wachtwoord',
        'type' => 'private',
    ])->assertRedirect(route('verification.overview'));

    $user = User::where('email', 'pieter@example.nl')->sole();

    expect($user->type)->toBe(UserType::Private)
        ->and($user->can_bid)->toBeFalse()
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(auth()->id())->toBe($user->id);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('registreert zakelijk mét bedrijf en kvk-nummer', function () {
    Notification::fake();

    $this->post('/registreren', [
        'terms' => '1',
        'name' => 'Willem de Boer',
        'email' => 'willem@keukenhuis.nl',
        'password' => 'geheim-wachtwoord',
        'password_confirmation' => 'geheim-wachtwoord',
        'type' => 'business',
        'company_name' => 'Keukenhuis Utrecht BV',
        'kvk_number' => '12345678',
    ])->assertRedirect(route('verification.overview'));

    $company = Company::where('kvk_number', '12345678')->sole();

    expect($company->name)->toBe('Keukenhuis Utrecht BV')
        ->and($company->verified_at)->toBeNull()
        ->and(User::where('email', 'willem@keukenhuis.nl')->sole()->company_id)->toBe($company->id);
});

it('eist bedrijfsgegevens bij een zakelijk account', function () {
    $this->post('/registreren', [
        'terms' => '1',
        'name' => 'Zonder Bedrijf',
        'email' => 'zonder@example.nl',
        'password' => 'geheim-wachtwoord',
        'password_confirmation' => 'geheim-wachtwoord',
        'type' => 'business',
    ])->assertSessionHasErrors(['company_name', 'kvk_number']);
});

it('zet een funnelconcept op naam bij registratie', function () {
    // Wie in de funnel begon en zich pas daarna registreert, houdt zijn werk.
    $this->withCredentials()->postJson('/api/drafts');
    $lot = Lot::sole();

    Notification::fake();

    $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
        ->post('/registreren', [
            'terms' => '1',
            'name' => 'Funnel Verkoper',
            'email' => 'funnel@example.nl',
            'password' => 'geheim-wachtwoord',
            'password_confirmation' => 'geheim-wachtwoord',
            'type' => 'private',
        ]);

    expect($lot->fresh()->seller_id)
        ->toBe(User::where('email', 'funnel@example.nl')->sole()->id);
});

it('weigert een e-mailadres dat al bestaat', function () {
    User::factory()->create(['email' => 'bezet@example.nl']);

    $this->post('/registreren', [
        'terms' => '1',
        'name' => 'Dubbel',
        'email' => 'bezet@example.nl',
        'password' => 'geheim-wachtwoord',
        'password_confirmation' => 'geheim-wachtwoord',
        'type' => 'private',
    ])->assertSessionHasErrors('email');
});

it('vraagt akkoord op de voorwaarden en één keer het wachtwoord', function () {
    Notification::fake();

    $this->get('/registreren')
        ->assertOk()
        ->assertSee('Ik wil kopen')
        ->assertSee('Ik wil verkopen')
        ->assertSee('Alleen voor bedrijven')
        ->assertDontSee('nog een keer');

    // Zonder akkoord komt er geen account.
    $this->post('/registreren', [
        'name' => 'Sanne Visser',
        'email' => 'sanne@example.nl',
        'password' => 'geheim-wachtwoord',
        'type' => 'private',
    ])->assertSessionHasErrors('terms');

    expect(User::where('email', 'sanne@example.nl')->exists())->toBeFalse();

    // Met akkoord volstaat één wachtwoordveld, zoals het formulier het vraagt.
    $this->post('/registreren', [
        'terms' => '1',
        'name' => 'Sanne Visser',
        'email' => 'sanne@example.nl',
        'password' => 'geheim-wachtwoord',
        'type' => 'private',
    ])->assertRedirect(route('verification.overview'));
});
