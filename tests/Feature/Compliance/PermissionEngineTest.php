<?php

declare(strict_types=1);

use App\Compliance\DenialReason;
use App\Compliance\Permission;
use App\Compliance\PermissionEngine;
use App\Enums\KycStatus;
use App\Models\Company;
use App\Models\User;

/*
| De Permission Engine is de enige plek die beslist of iemand mag bieden,
| publiceren of uitbetaald worden (briefing sectie 7, flow E). De UI verbergt
| alleen; hier wordt geblokkeerd.
*/

function engine(): PermissionEngine
{
    return app(PermissionEngine::class);
}

it('laat een geverifieerd particulier account bieden, publiceren en uitbetaald worden', function () {
    $user = User::factory()->verified()->create();

    foreach (Permission::cases() as $permission) {
        expect(engine()->decide($user, $permission)->allowed)->toBeTrue($permission->value);
    }
});

it('weigert alles voor een geblokkeerd account, als eerste reden', function () {
    $user = User::factory()->verified()->create(['blocked_at' => now(), 'blocked_reason' => 'fraudevermoeden']);

    $decision = engine()->decide($user, Permission::Bid);

    expect($decision->denied())->toBeTrue()
        ->and($decision->reason)->toBe(DenialReason::Blocked)
        ->and($decision->reason->resolutionRoute())->toBeNull();
});

it('vraagt eerst een bevestigd e-mailadres, dan de identiteitscontrole', function () {
    $unverifiedMail = User::factory()->unverified()->create();
    $noKyc = User::factory()->create(['kyc_status' => KycStatus::Unverified]);
    $pending = User::factory()->create(['kyc_status' => KycStatus::Pending]);
    $rejected = User::factory()->create(['kyc_status' => KycStatus::Rejected]);

    expect(engine()->decide($unverifiedMail, Permission::Bid)->reason)->toBe(DenialReason::EmailUnverified)
        ->and(engine()->decide($noKyc, Permission::Bid)->reason)->toBe(DenialReason::KycRequired)
        ->and(engine()->decide($pending, Permission::Bid)->reason)->toBe(DenialReason::KycPending)
        ->and(engine()->decide($rejected, Permission::Bid)->reason)->toBe(DenialReason::KycRejected);
});

it('laat een zakelijk account pas publiceren na de bedrijfscontrole', function () {
    $company = Company::factory()->create(['verified_at' => null]);
    $seller = User::factory()->verified()->create(['type' => 'business', 'company_id' => $company->id]);

    expect(engine()->decide($seller, Permission::Bid)->allowed)->toBeTrue()
        ->and(engine()->decide($seller, Permission::Publish)->reason)->toBe(DenialReason::KybRequired);

    $company->forceFill(['verified_at' => now()])->save();

    expect(engine()->decide($seller->fresh(), Permission::Publish)->allowed)->toBeTrue();
});

it('houdt een uitbetaling tegen zonder rekeningnummer op het bedrijf', function () {
    $company = Company::factory()->create(['verified_at' => now(), 'payout_iban_last4' => null]);
    $seller = User::factory()->verified()->create(['type' => 'business', 'company_id' => $company->id]);

    expect(engine()->decide($seller, Permission::Publish)->allowed)->toBeTrue()
        ->and(engine()->decide($seller, Permission::Payout)->reason)->toBe(DenialReason::IbanRequired);
});

it('heeft voor elke reden een tekst in gewone taal', function () {
    foreach (DenialReason::cases() as $reason) {
        expect($reason->message())->not->toBe('permissions.'.$reason->value);
    }
});
