<?php

declare(strict_types=1);

use App\Compliance\Actions\DecideKyc;
use App\Enums\KycStatus;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/*
| De verificatieroute (hfst. 13.6): e-mail → telefoon → identiteit → KVK.
| Bieden vereist e-mail + identiteit; de harde gate zit in PlaceBid en de
| vlag wordt alleen via refreshBidPermission gezet.
*/

function freshUser(array $attributes = []): User
{
    return User::factory()->create([
        'email_verified_at' => null,
        'kyc_status' => KycStatus::Unverified,
        'can_bid' => false,
        ...$attributes,
    ]);
}

it('bevestigt het e-mailadres via de ondertekende link', function () {
    $user = freshUser();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('verification.overview'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        // E-mail alleen is niet genoeg om te bieden.
        ->and($user->fresh()->can_bid)->toBeFalse();
});

it('weigert de identiteitsstap vóór het e-mailadres bevestigd is', function () {
    $user = freshUser();

    $this->actingAs($user)
        ->post('/verificatie/identiteit')
        ->assertSessionHasErrors('identity');

    expect($user->fresh()->kyc_status)->toBe(KycStatus::Unverified);
});

it('zet een aangevraagde identiteitscontrole in behandeling, nog zonder biedvlag', function () {
    $user = freshUser(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post('/verificatie/identiteit')
        ->assertRedirect(route('verification.overview'));

    $user->refresh();

    expect($user->kyc_status)->toBe(KycStatus::Pending)
        ->and($user->kyc_requested_at)->not->toBeNull()
        ->and($user->kyc_verified_at)->toBeNull()
        ->and($user->can_bid)->toBeFalse();

    $this->actingAs($user)->get('/verificatie')->assertOk()->assertSee('in behandeling');
});

it('zet de biedvlag pas na e-mail én een goedgekeurde identiteit', function () {
    $user = freshUser(['email_verified_at' => now(), 'kyc_status' => KycStatus::Pending]);

    app(DecideKyc::class)->handle($user, User::factory()->create(['is_staff' => true]), true);

    $user->refresh();

    expect($user->kyc_status)->toBe(KycStatus::Verified)
        ->and($user->kyc_verified_at)->not->toBeNull()
        ->and($user->can_bid)->toBeTrue();
});

it('legt het telefoonnummer vast', function () {
    $user = freshUser();

    $this->actingAs($user)
        ->post('/verificatie/telefoon', ['phone' => '+31 6 12345678'])
        ->assertRedirect(route('verification.overview'));

    // Opgeslagen als internationaal nummer, nog niet bevestigd: dat gebeurt
    // pas met de code uit de sms (PhoneVerificationTest).
    expect($user->fresh()->phone)->toBe('+31612345678')
        ->and($user->fresh()->phone_verified_at)->toBeNull();
});

it('koppelt de kvk alleen voor zakelijke accounts', function () {
    $private = freshUser();

    $this->actingAs($private)
        ->post('/verificatie/kvk')
        ->assertSessionHasErrors('kvk');

    $business = User::factory()->business()->create();
    $business->company->verified_at = null;
    $business->company->save();

    $this->actingAs($business)
        ->post('/verificatie/kvk')
        ->assertRedirect(route('verification.overview'));

    expect($business->company->fresh()->verified_at)->toBeNull()
        ->and($business->company->fresh()->awaitsVerification())->toBeTrue();
});

it('toont de checklist met de juiste stappen', function () {
    $user = freshUser();

    $this->actingAs($user)
        ->get('/verificatie')
        ->assertOk()
        ->assertSee('E-mail bevestigd')
        ->assertSee('Identiteit')
        ->assertSee('alleen het resultaat en de datum');
});
