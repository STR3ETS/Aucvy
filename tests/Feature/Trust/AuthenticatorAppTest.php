<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use App\Support\TwoFactor\Authenticator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;

/*
| Tweede factor via een authenticator-app (besluit Raph, 2 november 2026):
| TOTP met herstelcodes, de mailcode blijft als terugval. Koppelen en
| ontkoppelen vraagt het wachtwoord.
*/

beforeEach(function () {
    Notification::fake();
});

/**
 * Een gebruiker met een gekoppelde app; geeft het geheim terug.
 */
function withAuthenticator(User $user): string
{
    $secret = app(Authenticator::class)->newSecret();
    $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

    return $secret;
}

function currentCode(string $secret): string
{
    return (new Google2FA)->getCurrentOtp($secret);
}

it('koppelt een app pas als de eerste code klopt, en geeft herstelcodes', function () {
    $user = User::factory()->verified()->create(['password' => 'wachtwoord']);

    $this->actingAs($user)->post(route('account.two-factor.start'), ['password' => 'fout'])
        ->assertSessionHasErrors('password');

    $this->actingAs($user)->post(route('account.two-factor.start'), ['password' => 'wachtwoord'])
        ->assertRedirect();

    $secret = Crypt::decryptString(session('two_factor_setup'));

    $this->actingAs($user)->get(route('account.settings.section', 'beveiliging'))
        ->assertOk()
        ->assertSee('<svg', escape: false)
        ->assertSee(chunk_split($secret, 4, ' '));

    // Nog niets op de gebruiker zolang de code niet klopt.
    $this->actingAs($user)->post(route('account.two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code');
    expect($user->fresh()->hasAuthenticatorApp())->toBeFalse();

    $this->actingAs($user)->post(route('account.two-factor.confirm'), ['code' => currentCode($secret)])
        ->assertSessionHas('recovery_codes', fn (array $codes) => count($codes) === Authenticator::RECOVERY_CODE_COUNT);

    $user->refresh();

    expect($user->hasAuthenticatorApp())->toBeTrue()
        ->and($user->two_factor_secret)->toBe($secret)
        ->and($user->getRawOriginal('two_factor_secret'))->not->toContain($secret)
        ->and(AuditLog::query()->where('action', 'user.two_factor_enabled')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('vraagt bij inloggen de appcode en geen mail', function () {
    $user = User::factory()->verified()->create(['password' => 'wachtwoord']);
    $secret = withAuthenticator($user);

    $this->post('/inloggen', ['email' => $user->email, 'password' => 'wachtwoord'])
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
    Notification::assertNothingSent();

    $this->get(route('two-factor.challenge'))->assertOk()->assertSee('authenticator-app');

    $this->post(route('two-factor.store'), ['code' => currentCode($secret)])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('laat dezelfde appcode maar één keer werken', function () {
    $user = User::factory()->verified()->create();
    $secret = withAuthenticator($user);
    $code = currentCode($secret);

    expect(app(Authenticator::class)->verify($user, $code))->toBeTrue()
        ->and(app(Authenticator::class)->verify($user->fresh(), $code))->toBeFalse();
});

it('weigert een foute appcode en blijft uitgelogd', function () {
    $user = User::factory()->verified()->create(['password' => 'wachtwoord']);
    withAuthenticator($user);

    $this->post('/inloggen', ['email' => $user->email, 'password' => 'wachtwoord']);
    $this->post(route('two-factor.store'), ['code' => '000000'])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('logt in met een herstelcode, maar elke code maar één keer', function () {
    $user = User::factory()->verified()->create(['password' => 'wachtwoord']);
    withAuthenticator($user);
    $codes = app(Authenticator::class)->newRecoveryCodes($user);

    $this->post('/inloggen', ['email' => $user->email, 'password' => 'wachtwoord']);
    $this->post(route('two-factor.store'), ['recovery_code' => strtoupper($codes[0])])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    expect(app(Authenticator::class)->remainingRecoveryCodes($user->fresh()))->toBe(Authenticator::RECOVERY_CODE_COUNT - 1)
        ->and(AuditLog::query()->where('action', 'user.two_factor_recovery_used')->exists())->toBeTrue();

    $this->post('/uitloggen');
    $this->post('/inloggen', ['email' => $user->email, 'password' => 'wachtwoord']);
    $this->post(route('two-factor.store'), ['recovery_code' => $codes[0]])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('stuurt als terugval toch een mailcode, en die werkt dan', function () {
    $user = User::factory()->verified()->create(['password' => 'wachtwoord']);
    withAuthenticator($user);

    $this->post('/inloggen', ['email' => $user->email, 'password' => 'wachtwoord']);
    $this->post(route('two-factor.mail'))->assertRedirect(route('two-factor.challenge'));

    $code = null;
    Notification::assertSentTo($user, TwoFactorCode::class, function (TwoFactorCode $notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    $this->post(route('two-factor.store'), ['code' => $code])->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('ontkoppelt alleen met het wachtwoord', function () {
    $user = User::factory()->verified()->create(['password' => 'wachtwoord']);
    withAuthenticator($user);

    $this->actingAs($user)->delete(route('account.two-factor.disable'), ['password' => 'fout'])
        ->assertSessionHasErrors('password');
    expect($user->fresh()->hasAuthenticatorApp())->toBeTrue();

    $this->actingAs($user)->delete(route('account.two-factor.disable'), ['password' => 'wachtwoord'])
        ->assertRedirect();

    expect($user->fresh()->hasAuthenticatorApp())->toBeFalse()
        ->and($user->fresh()->two_factor_recovery_codes)->toBeNull()
        ->and(AuditLog::query()->where('action', 'user.two_factor_disabled')->exists())->toBeTrue();
});

it('vervangt herstelcodes, waarna de oude niet meer werken', function () {
    $user = User::factory()->verified()->create(['password' => 'wachtwoord']);
    withAuthenticator($user);
    $old = app(Authenticator::class)->newRecoveryCodes($user);

    $this->actingAs($user)->post(route('account.two-factor.recovery'), ['password' => 'wachtwoord'])
        ->assertSessionHas('recovery_codes');

    expect(app(Authenticator::class)->useRecoveryCode($user->fresh(), $old[0]))->toBeFalse();
});
