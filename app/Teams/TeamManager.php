<?php

declare(strict_types=1);

namespace App\Teams;

use App\Enums\CompanyRole;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\TeamInvitation;
use App\Notifications\TeamMemberJoined;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Het team van een bedrijfsaccount (briefing sectie 2: organisatierollen).
 * Uitnodigen gaat per mail met een link; rollen wijzigen en verwijderen
 * doet een eigenaar of beheerder. Er blijft altijd minstens één eigenaar.
 */
final class TeamManager
{
    public function invite(Company $company, User $inviter, string $email, CompanyRole $role): CompanyInvitation
    {
        $email = Str::lower(trim($email));

        if ($company->users()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => __('team.errors.already_member')]);
        }

        if (User::query()->where('email', $email)->whereNotNull('company_id')->exists()) {
            throw ValidationException::withMessages(['email' => __('team.errors.other_company')]);
        }

        $invitation = DB::transaction(function () use ($company, $inviter, $email, $role): CompanyInvitation {
            // Eén open uitnodiging per adres: een nieuwe vervangt de vorige.
            $company->invitations()->where('email', $email)->whereNull('accepted_at')->delete();

            $invitation = $company->invitations()->create([
                'invited_by' => $inviter->id,
                'email' => $email,
                'role' => $role,
                'token' => Str::random(48),
                'expires_at' => now()->addDays(CompanyInvitation::VALID_DAYS),
            ]);

            Audit::record('team.invited', $company, $inviter, after: ['email' => $email, 'role' => $role->value]);

            return $invitation;
        });

        Notification::route('mail', $email)->notify(new TeamInvitation($invitation));

        return $invitation;
    }

    public function accept(CompanyInvitation $invitation, User $user): void
    {
        if (! $invitation->isOpen()) {
            throw ValidationException::withMessages(['invitation' => __('team.errors.expired')]);
        }

        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            throw ValidationException::withMessages(['invitation' => __('team.errors.wrong_account', ['email' => $invitation->email])]);
        }

        if ($user->company_id !== null && $user->company_id !== $invitation->company_id) {
            throw ValidationException::withMessages(['invitation' => __('team.errors.other_company_self')]);
        }

        DB::transaction(function () use ($invitation, $user): void {
            $user->forceFill([
                'company_id' => $invitation->company_id,
                'company_role' => $invitation->role,
                'type' => UserType::Business,
            ])->save();

            $invitation->forceFill(['accepted_at' => now()])->save();

            Audit::record('team.joined', $invitation->company, $user, after: ['role' => $invitation->role->value]);
        });

        // De uitnodiger hoort het; is die weg, dan de eigenaren.
        $recipients = $invitation->inviter !== null && $invitation->inviter->company_id === $invitation->company_id
            ? collect([$invitation->inviter])
            : $invitation->company->users()->where('company_role', CompanyRole::Owner->value)->get();

        $recipients->reject(fn (User $recipient): bool => $recipient->is($user))
            ->each(fn (User $recipient) => $recipient->notify(new TeamMemberJoined($user, $invitation->role->label())));
    }

    public function changeRole(Company $company, User $actor, User $member, CompanyRole $role): void
    {
        $this->guardMember($company, $member);

        if ($member->is($actor)) {
            throw ValidationException::withMessages(['role' => __('team.errors.own_role')]);
        }

        if ($member->company_role === CompanyRole::Owner && $role !== CompanyRole::Owner) {
            $this->guardLastOwner($company, $member);
        }

        $before = $member->company_role?->value;
        $member->forceFill(['company_role' => $role])->save();

        Audit::record('team.role_changed', $member, $actor, ['role' => $before], ['role' => $role->value]);
    }

    public function remove(Company $company, User $actor, User $member): void
    {
        $this->guardMember($company, $member);

        if ($member->is($actor)) {
            throw ValidationException::withMessages(['member' => __('team.errors.remove_self')]);
        }

        if ($member->company_role === CompanyRole::Owner) {
            $this->guardLastOwner($company, $member);
        }

        $member->forceFill(['company_id' => null, 'company_role' => null])->save();

        Audit::record('team.removed', $company, $actor, after: ['user_id' => $member->id, 'email' => $member->email]);
    }

    public function revoke(Company $company, User $actor, CompanyInvitation $invitation): void
    {
        abort_unless($invitation->company_id === $company->id, 404);

        $invitation->delete();

        Audit::record('team.invitation_revoked', $company, $actor, after: ['email' => $invitation->email]);
    }

    private function guardMember(Company $company, User $member): void
    {
        abort_unless($member->company_id === $company->id, 404);
    }

    private function guardLastOwner(Company $company, User $member): void
    {
        $otherOwners = $company->users()->where('company_role', CompanyRole::Owner->value)->whereKeyNot($member->id)->exists();

        if (! $otherOwners) {
            throw ValidationException::withMessages(['role' => __('team.errors.last_owner')]);
        }
    }
}
