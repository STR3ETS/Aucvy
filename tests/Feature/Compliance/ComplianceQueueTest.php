<?php

declare(strict_types=1);

use App\Compliance\Permission;
use App\Compliance\PermissionEngine;
use App\Enums\KycStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\VerificationDecided;
use Illuminate\Support\Facades\Notification;

/*
| De compliance-queue (briefing: admin P0, flow E): een aangevraagde
| identiteits- of bedrijfscontrole wacht op een besluit van beheer; het
| besluit zet de rechten, laat een spoor na en mailt de aanvrager. Blokkeren
| sluit geld en publicatie af tot iemand het weer vrijgeeft.
*/

beforeEach(function () {
    Notification::fake();
    $this->staff = User::factory()->create(['is_staff' => true]);
});

it('zet een aangevraagde identiteitscontrole in de wachtrij', function () {
    $user = User::factory()->create(['kyc_status' => KycStatus::Unverified]);

    $this->actingAs($user)->post('/verificatie/identiteit')->assertRedirect(route('verification.overview'));

    $user->refresh();

    expect($user->kyc_status)->toBe(KycStatus::Pending)
        ->and($user->kyc_requested_at)->not->toBeNull()
        ->and($user->can_bid)->toBeFalse();

    $this->actingAs($this->staff)->get('/admin/compliance')
        ->assertOk()
        ->assertSee($user->name)
        ->assertSee(route('admin.compliance.kyc', $user), escape: false);
});

it('keurt een identiteit goed: biedvlag aan, spoor, mail', function () {
    $user = User::factory()->create(['kyc_status' => KycStatus::Pending, 'kyc_requested_at' => now()]);

    $this->actingAs($this->staff)
        ->post(route('admin.compliance.kyc', $user), ['decision' => 'approve'])
        ->assertRedirect();

    $user->refresh();

    expect($user->kyc_status)->toBe(KycStatus::Verified)
        ->and($user->can_bid)->toBeTrue()
        ->and(app(PermissionEngine::class)->allows($user, Permission::Bid))->toBeTrue();

    $entry = AuditLog::query()->where('action', 'user.kyc_changed')->where('subject_id', $user->id)->latest('id')->first();
    expect($entry->actor_id)->toBe($this->staff->id)
        ->and($entry->before)->toBe(['status' => 'pending'])
        ->and($entry->after['status'])->toBe('verified');

    Notification::assertSentTo($user, VerificationDecided::class, fn (VerificationDecided $mail) => $mail->status === KycStatus::Verified && $mail->kind === 'kyc');
});

it('wijst een identiteit af met reden, en de reden gaat mee in de mail', function () {
    $user = User::factory()->create(['kyc_status' => KycStatus::Pending]);

    $this->actingAs($this->staff)
        ->from('/admin/compliance')
        ->post(route('admin.compliance.kyc', $user), ['decision' => 'reject'])
        ->assertSessionHasErrors('note');

    $this->actingAs($this->staff)
        ->post(route('admin.compliance.kyc', $user), ['decision' => 'reject', 'note' => 'Naam op het document wijkt af van het account.'])
        ->assertRedirect();

    $user->refresh();

    expect($user->kyc_status)->toBe(KycStatus::Rejected)
        ->and($user->kyc_note)->toContain('wijkt af')
        ->and($user->can_bid)->toBeFalse();

    Notification::assertSentTo($user, VerificationDecided::class, fn (VerificationDecided $mail) => $mail->status === KycStatus::Rejected && str_contains((string) $mail->reason, 'wijkt af'));
});

it('zet een KvK-koppeling in de wachtrij en keurt het bedrijf goed', function () {
    $seller = User::factory()->verified()->business()->create();
    $company = $seller->company;
    $company->forceFill(['verified_at' => null])->save();

    $this->actingAs($seller)->post('/verificatie/kvk')->assertRedirect(route('verification.overview'));

    expect($company->fresh()->verified_at)->toBeNull()
        ->and($company->fresh()->kyb_requested_at)->not->toBeNull()
        ->and(app(PermissionEngine::class)->allows($seller->fresh(), Permission::Publish))->toBeFalse();

    $this->actingAs($this->staff)->get('/admin/compliance')->assertOk()->assertSee($company->name);

    $this->actingAs($this->staff)
        ->post(route('admin.compliance.kyb', $company), ['decision' => 'approve'])
        ->assertRedirect();

    expect($company->fresh()->isVerified())->toBeTrue()
        ->and(app(PermissionEngine::class)->allows($seller->fresh(), Permission::Publish))->toBeTrue();

    Notification::assertSentTo($seller, VerificationDecided::class, fn (VerificationDecided $mail) => $mail->kind === 'kyb');
});

it('blokkeert en deblokkeert een account met reden en spoor', function () {
    $user = User::factory()->verified()->create();

    $this->actingAs($this->staff)
        ->post(route('admin.compliance.block', $user), ['reason' => 'Vermoeden van nepbiedingen op eigen kavels.'])
        ->assertRedirect();

    expect($user->fresh()->isBlocked())->toBeTrue()
        ->and(app(PermissionEngine::class)->allows($user->fresh(), Permission::Bid))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'user.blocked')->where('subject_id', $user->id)->exists())->toBeTrue();

    $this->actingAs($this->staff)->get('/admin/compliance')->assertOk()->assertSee('nepbiedingen');

    $this->actingAs($this->staff)
        ->post(route('admin.compliance.unblock', $user), ['note' => 'Onderzocht, bleek een vergissing.'])
        ->assertRedirect();

    expect($user->fresh()->isBlocked())->toBeFalse()
        ->and(app(PermissionEngine::class)->allows($user->fresh(), Permission::Bid))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user.unblocked')->where('subject_id', $user->id)->exists())->toBeTrue();
});

it('keurt een identiteit direct goed als dat in de config staat', function () {
    config()->set('aucvy.verification.auto_approve', true);
    $user = User::factory()->create(['kyc_status' => KycStatus::Unverified]);

    $this->actingAs($user)->post('/verificatie/identiteit')->assertRedirect();

    expect($user->fresh()->kyc_status)->toBe(KycStatus::Verified)
        ->and($user->fresh()->can_bid)->toBeTrue();
});
