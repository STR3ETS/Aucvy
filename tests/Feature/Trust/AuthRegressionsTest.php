<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use App\Support\Health\Health;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

/*
| Review van de authenticatie en de infrastructuur (blok T): "ingelogd
| blijven" omzeilt de code niet, een nieuw wachtwoord zet andere apparaten
| buiten, en de systeempagina is rood als productie op nepgeld draait.
*/

function staffWithRemember(string $token = 'oude-herinnering'): User
{
    $staff = User::factory()->create(['is_staff' => true]);
    $staff->setRememberToken($token);
    $staff->save();

    return $staff;
}

function otherSession(User $user): string
{
    config(['session.driver' => 'database']);

    DB::table('sessions')->insert([
        'id' => 'ander-apparaat-'.$user->id,
        'user_id' => $user->id,
        'ip_address' => '10.0.0.2',
        'user_agent' => 'Firefox',
        'payload' => base64_encode(serialize([])),
        'last_activity' => now()->getTimestamp(),
    ]);

    return 'ander-apparaat-'.$user->id;
}

describe('de tweede factor', function () {
    it('geeft na de code geen herinneringscookie aan wie een code moet geven', function () {
        Notification::fake();
        $staff = User::factory()->create(['is_staff' => true]);
        $code = null;

        $this->post('/inloggen', ['email' => $staff->email, 'password' => 'password', 'remember' => '1'])
            ->assertRedirect(route('two-factor.challenge'));

        Notification::assertSentTo($staff, TwoFactorCode::class, function (TwoFactorCode $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        $this->post('/inloggen/code', ['code' => $code])
            ->assertRedirect()
            ->assertCookieMissing(Auth::guard()->getRecallerName());

        $this->assertAuthenticatedAs($staff);
    });

    it('weigert een herinneringscookie van wie inmiddels een code moet geven', function () {
        $staff = staffWithRemember('abc');

        $this->withCookie(Auth::guard()->getRecallerName(), "{$staff->id}|abc|{$staff->password}")
            ->get('/admin')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        expect($staff->fresh()->remember_token)->not->toBe('abc');
    });

    it('laat de wachtwoordstap de herinnering van andere apparaten met rust', function () {
        Notification::fake();
        $staff = staffWithRemember('abc');

        $this->post('/inloggen', ['email' => $staff->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'));

        expect($staff->fresh()->remember_token)->toBe('abc');
        $this->assertGuest();
    });

    it('stuurt de code niet via de wachtrij', function () {
        expect(new TwoFactorCode('123456'))->not->toBeInstanceOf(ShouldQueue::class);
    });
});

describe('inloggen', function () {
    it('is ook per adres begrensd', function () {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/inloggen', ['email' => "poging{$i}@example.com", 'password' => 'fout'])->assertRedirect();
        }

        $this->post('/inloggen', ['email' => 'poging21@example.com', 'password' => 'fout'])->assertStatus(429);
    });
});

describe('wachtwoord en sessies', function () {
    it('zet bij een nieuw wachtwoord alle andere apparaten buiten, ook met herinneringscookie', function () {
        $user = User::factory()->verified()->create();
        $user->setRememberToken('abc');
        $user->save();
        $other = otherSession($user);

        $this->actingAs($user)
            ->patch(route('account.password'), [
                'current_password' => 'password',
                'password' => 'nieuw-wachtwoord-456',
                'password_confirmation' => 'nieuw-wachtwoord-456',
            ])
            ->assertRedirect();

        expect(DB::table('sessions')->where('id', $other)->exists())->toBeFalse()
            ->and($user->fresh()->remember_token)->not->toBe('abc');
    });

    it('wisselt bij "andere apparaten uitloggen" ook de herinneringstoken', function () {
        $user = User::factory()->verified()->create();
        $user->setRememberToken('abc');
        $user->save();
        $other = otherSession($user);

        $this->actingAs($user)
            ->post(route('account.sessions.logout'), ['password' => 'password'])
            ->assertRedirect();

        expect(DB::table('sessions')->where('id', $other)->exists())->toBeFalse()
            ->and($user->fresh()->remember_token)->not->toBe('abc');
    });

    it('beëindigt bij herstel alle sessies en geeft één melding voor onbekend adres en fout token', function () {
        $user = User::factory()->verified()->create();
        $user->setRememberToken('abc');
        $user->save();
        $other = otherSession($user);
        $token = Password::createToken($user);

        $this->post(route('password.update'), [
            'token' => 'vervalst', 'email' => $user->email,
            'password' => 'nieuw-wachtwoord-456', 'password_confirmation' => 'nieuw-wachtwoord-456',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'niemand@example.com',
            'password' => 'nieuw-wachtwoord-456', 'password_confirmation' => 'nieuw-wachtwoord-456',
        ])->assertSessionHasErrors(['email' => __('passwords.token')]);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'nieuw-wachtwoord-456', 'password_confirmation' => 'nieuw-wachtwoord-456',
        ])->assertRedirect(route('login'));

        expect(DB::table('sessions')->where('id', $other)->exists())->toBeFalse()
            ->and($user->fresh()->remember_token)->not->toBe('abc');
    });

    it('eist in productie een langer wachtwoord met letters en cijfers', function () {
        app()->detectEnvironment(fn (): string => 'production');

        try {
            expect(Validator::make(['password' => 'wachtwoord'], ['password' => PasswordRule::defaults()])->fails())->toBeTrue()
                ->and(Validator::make(['password' => 'Kerstboom2026'], ['password' => PasswordRule::defaults()])->fails())->toBeFalse();
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }
    });
});

describe('de uitbetaalrekening', function () {
    it('wijzigt alleen met het wachtwoord opnieuw', function () {
        $company = Company::factory()->create();
        $owner = User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => 'owner']);

        $this->actingAs($owner)
            ->patch(route('organisation.payout'), ['iban' => 'NL91ABNA0417164300', 'iban_name' => 'Keukenhuis'])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($owner)
            ->patch(route('organisation.payout'), ['current_password' => 'fout', 'iban' => 'NL91ABNA0417164300', 'iban_name' => 'Keukenhuis'])
            ->assertSessionHasErrors('current_password');

        expect($company->fresh()->payout_iban_last4)->toBeNull();
    });
});

describe('de infrastructuur', function () {
    it('laat een gedode run de planner niet een dag blokkeren', function () {
        $events = collect(app(Schedule::class)->events());
        $closing = $events->first(fn ($event): bool => str_contains((string) $event->command, 'auctions:close-overdue'));
        $advancing = $events->first(fn ($event): bool => str_contains((string) $event->command, 'orders:advance'));

        expect($closing->expiresAt)->toBe(5)
            ->and($advancing->expiresAt)->toBe(5);
    });

    it('kleurt de systeempagina rood bij productie-instellingen die geld of identiteit raken', function () {
        app()->detectEnvironment(fn (): string => 'production');
        config([
            'app.debug' => false,
            'mail.default' => 'smtp',
            'mail.from.address' => 'info@aucvy.nl',
            'aucvy.ai.provider' => 'anthropic',
            'aucvy.payments.provider' => 'fake',
            'aucvy.verification.auto_approve' => true,
            'aucvy.support.email' => '',
            'broadcasting.default' => 'log',
            'queue.default' => 'sync',
            'session.secure' => null,
        ]);

        try {
            $settings = collect(app(Health::class)->checks())->firstWhere('key', 'settings');
        } finally {
            app()->detectEnvironment(fn (): string => 'testing');
        }

        expect($settings['status'])->toBe('fail')
            ->and($settings['value'])->toContain('nepprovider')
            ->and($settings['value'])->toContain('zichzelf goed')
            ->and($settings['value'])->toContain('supportadres')
            ->and($settings['value'])->toContain('reverb')
            ->and($settings['value'])->toContain('synchroon')
            ->and($settings['value'])->toContain('SESSION_SECURE_COOKIE');
    });
});
