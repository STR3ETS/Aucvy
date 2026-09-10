<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use Illuminate\Support\Facades\Notification;

/*
| 2FA verplicht bij een gekoppelde uitbetaling (hfst. 15): wie geld kan
| ontvangen, logt in twee stappen in. Voor iedereen zonder uitbetaling
| verandert er niets.
*/

it('logt een gewone gebruiker direct in', function () {
    $user = User::factory()->verified()->create(['password' => 'wachtwoord']);

    $this->post('/inloggen', ['email' => $user->email, 'password' => 'wachtwoord'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('vraagt een code zodra er een uitbetaling aan het account hangt', function () {
    Notification::fake();

    $company = Company::factory()->create(['payout_iban_last4' => '4821']);
    $seller = User::factory()->verified()->create([
        'password' => 'wachtwoord',
        'company_id' => $company->id,
    ]);

    $this->post('/inloggen', ['email' => $seller->email, 'password' => 'wachtwoord'])
        ->assertRedirect(route('two-factor.challenge'));

    // Nog níét ingelogd: de code is de tweede helft van het wachtwoord.
    $this->assertGuest();
    Notification::assertSentTo($seller, TwoFactorCode::class);
});

it('logt in met de juiste code en weigert een foute', function () {
    Notification::fake();

    $company = Company::factory()->create(['payout_iban_last4' => '4821']);
    $seller = User::factory()->verified()->create([
        'password' => 'wachtwoord',
        'company_id' => $company->id,
    ]);

    $this->post('/inloggen', ['email' => $seller->email, 'password' => 'wachtwoord']);

    $code = null;
    Notification::assertSentTo($seller, TwoFactorCode::class, function (TwoFactorCode $notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    // Fout eerst: blijft uitgelogd, foutmelding op het codeveld.
    $this->post('/inloggen/code', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->assertGuest();

    // Dan goed: ingelogd, en de code is daarna waardeloos.
    $this->post('/inloggen/code', ['code' => $code])->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($seller);
});

it('stuurt je terug naar inloggen zonder lopende tweede stap', function () {
    $this->get('/inloggen/code')->assertRedirect(route('login'));
});
