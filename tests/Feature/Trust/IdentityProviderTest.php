<?php

declare(strict_types=1);

use App\Compliance\Identity\FakeIdentityProvider;
use App\Enums\KycStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\VerificationDecided;
use Illuminate\Support\Facades\Notification;

/*
| Identiteit via de bank (besluit Raph, 2 november 2026: iDIN, partij V7).
| Met de nepbank dezelfde reis als straks met de echte partij; zonder
| partij blijft de handmatige compliance-queue zoals hij was.
*/

beforeEach(function () {
    Notification::fake();
    config(['aucvy.identity.provider' => 'fake']);
    $this->user = User::factory()->create(['kyc_status' => KycStatus::Unverified, 'email_verified_at' => now()]);
});

it('stuurt de gebruiker naar de bank en verifieert bij terugkeer', function () {
    $redirect = $this->actingAs($this->user)->post(route('verification.identity'))->assertRedirect()->headers->get('Location');

    expect($redirect)->toContain('/verificatie/identiteit/nepbank');

    $this->actingAs($this->user)->get($redirect)->assertOk()->assertSee('Identiteit bevestigen');

    $this->actingAs($this->user)->get(FakeIdentityProvider::returnUrl($this->user, 'ok'))
        ->assertRedirect(route('verification.overview'))
        ->assertSessionHas('status');

    $this->user->refresh();

    expect($this->user->kyc_status)->toBe(KycStatus::Verified)
        ->and(AuditLog::query()->where('action', 'user.kyc_changed')->latest('id')->first()->after['via'])->toBe('fake');

    Notification::assertSentTo($this->user, VerificationDecided::class);
});

it('verifieert niet bij annuleren, een minderjarige of een zelfgemaakte terugkeer', function () {
    $this->actingAs($this->user)->get(FakeIdentityProvider::returnUrl($this->user, 'cancel'))->assertSessionHasErrors('identity');
    $this->actingAs($this->user)->get(FakeIdentityProvider::returnUrl($this->user, 'ok', adult: false))->assertSessionHasErrors('identity');
    $this->actingAs($this->user)->get(route('verification.identity.return', ['user' => $this->user->id, 'status' => 'ok']))->assertSessionHasErrors('identity');

    expect($this->user->fresh()->kyc_status)->toBe(KycStatus::Unverified)
        ->and(AuditLog::query()->where('action', 'user.identity_failed')->count())->toBe(3);
});

it('laat een terugkeer voor een andere gebruiker niets doen', function () {
    $other = User::factory()->create(['kyc_status' => KycStatus::Unverified]);

    $this->actingAs($this->user)->get(FakeIdentityProvider::returnUrl($other, 'ok'))->assertSessionHasErrors('identity');

    expect($this->user->fresh()->kyc_status)->toBe(KycStatus::Unverified);
});

it('houdt zonder partij de handmatige controle', function () {
    config(['aucvy.identity.provider' => 'manual']);

    $this->actingAs($this->user)->post(route('verification.identity'))->assertRedirect(route('verification.overview'));

    expect($this->user->fresh()->kyc_status)->toBe(KycStatus::Pending);
});
