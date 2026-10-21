<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\TeamMemberJoined;
use Illuminate\Support\Facades\Notification;

/*
| Neemt een collega de uitnodiging aan, dan hoort de uitnodiger dat; is die
| weg bij het bedrijf, dan de eigenaren.
*/

it('meldt de uitnodiger dat de collega erbij is', function () {
    Notification::fake();

    $company = Company::factory()->verified()->create();
    $owner = User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => CompanyRole::Owner, 'type' => 'business']);
    $admin = User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => CompanyRole::Admin, 'type' => 'business']);
    $invitation = CompanyInvitation::factory()->create(['company_id' => $company->id, 'invited_by' => $admin->id, 'email' => 'nieuw@bedrijf.nl', 'role' => CompanyRole::Editor]);
    $invitee = User::factory()->create(['email' => 'nieuw@bedrijf.nl']);

    $this->actingAs($invitee)->post(route('team.accept', $invitation))->assertRedirect(route('team.index'));

    Notification::assertSentTo($admin, TeamMemberJoined::class, fn (TeamMemberJoined $mail) => $mail->member->is($invitee) && $mail->roleLabel === 'Redacteur');
    Notification::assertNotSentTo($owner, TeamMemberJoined::class);
    Notification::assertNotSentTo($invitee, TeamMemberJoined::class);
});

it('valt terug op de eigenaren als de uitnodiger het bedrijf verlaten heeft', function () {
    Notification::fake();

    $company = Company::factory()->verified()->create();
    $owner = User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => CompanyRole::Owner, 'type' => 'business']);
    $gone = User::factory()->verified()->create(['company_id' => null, 'company_role' => null]);
    $invitation = CompanyInvitation::factory()->create(['company_id' => $company->id, 'invited_by' => $gone->id, 'email' => 'nieuw@bedrijf.nl', 'role' => CompanyRole::Finance]);
    $invitee = User::factory()->create(['email' => 'nieuw@bedrijf.nl']);

    $this->actingAs($invitee)->post(route('team.accept', $invitation))->assertRedirect(route('team.index'));

    Notification::assertSentTo($owner, TeamMemberJoined::class);
    Notification::assertNotSentTo($gone, TeamMemberJoined::class);
});
