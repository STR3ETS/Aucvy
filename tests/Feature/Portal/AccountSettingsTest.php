<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
| Profiel en instellingen (briefing sectie 2): gegevens, wachtwoord,
| meldingsvoorkeuren, sessies en apparaten.
*/

it('toont de instellingen met de voorkeuren en het huidige apparaat', function () {
    $user = User::factory()->create(['notification_prefs' => ['alerts_email' => false]]);

    $this->actingAs($user)->get(route('account.settings'))
        ->assertOk()
        ->assertSee($user->email)
        ->assertSee('Meldingen')
        ->assertSee('Apparaten');
});

it('werkt naam en telefoon bij en laat een nieuw nummer opnieuw bevestigen', function () {
    $user = User::factory()->verified()->create();

    $this->actingAs($user)
        ->patch(route('account.profile'), ['name' => 'Anna de Vries', 'phone' => '+31 6 99999999'])
        ->assertRedirect();

    $user->refresh();

    expect($user->name)->toBe('Anna de Vries')
        ->and($user->phone)->toBe('+31 6 99999999')
        ->and($user->phone_verified_at)->toBeNull();
});

it('wijzigt het wachtwoord alleen met het huidige wachtwoord, met spoor', function () {
    $user = User::factory()->create(['password' => 'oud-wachtwoord-123']);

    $this->actingAs($user)
        ->from(route('account.settings'))
        ->patch(route('account.password'), ['current_password' => 'fout', 'password' => 'nieuw-wachtwoord-456', 'password_confirmation' => 'nieuw-wachtwoord-456'])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($user)
        ->patch(route('account.password'), ['current_password' => 'oud-wachtwoord-123', 'password' => 'nieuw-wachtwoord-456', 'password_confirmation' => 'nieuw-wachtwoord-456'])
        ->assertRedirect();

    expect(Hash::check('nieuw-wachtwoord-456', $user->fresh()->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user.password_changed')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('slaat meldingsvoorkeuren op; wat niet is aangevinkt staat uit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('account.notifications'), ['outbid_push' => 1, 'alerts_email' => 1])
        ->assertRedirect();

    $user->refresh();

    expect($user->prefers('outbid_push'))->toBeTrue()
        ->and($user->prefers('alerts_email'))->toBeTrue()
        ->and($user->prefers('outbid_email'))->toBeFalse()
        ->and($user->prefers('watchlist_push'))->toBeFalse();
});

it('logt andere apparaten uit met het wachtwoord', function () {
    $user = User::factory()->create(['password' => 'wachtwoord-123']);

    $this->actingAs($user)
        ->from(route('account.settings'))
        ->post(route('account.sessions.logout'), ['password' => 'fout'])
        ->assertSessionHasErrors('password');

    $this->actingAs($user)
        ->post(route('account.sessions.logout'), ['password' => 'wachtwoord-123'])
        ->assertRedirect();

    expect(AuditLog::query()->where('action', 'user.sessions_revoked')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('toont op het overzicht alleen de tegels en geeft elk onderdeel een eigen pagina', function () {
    $user = User::factory()->verified()->create();

    // Geen velden op het overzicht: elke tegel is een link naar zijn pagina.
    $this->actingAs($user)->get(route('account.settings'))
        ->assertOk()
        ->assertSee(route('account.settings.section', 'beveiliging'))
        ->assertSee(route('account.settings.section', 'gegevens'))
        ->assertDontSee('name="current_password"', escape: false)
        ->assertDontSee('name="display_name"', escape: false);

    $this->actingAs($user)->get(route('account.settings.section', 'gegevens'))->assertOk()->assertSee('name="display_name"', escape: false);
    $this->actingAs($user)->get(route('account.settings.section', 'beveiliging'))->assertOk()->assertSee('name="current_password"', escape: false)->assertSee('Tweede inlogstap');
    $this->actingAs($user)->get(route('account.settings.section', 'meldingen'))->assertOk()->assertSee('Overboden: push')->assertSee('Mail van Aucvy');
    $this->actingAs($user)->get(route('account.settings.section', 'apparaten'))->assertOk()->assertSee('Andere apparaten uitloggen');
    $this->actingAs($user)->get('/account/bestaat-niet')->assertNotFound();
});
