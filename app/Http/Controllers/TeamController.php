<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Teams\TeamManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Team van het bedrijfsaccount (briefing sectie 2: organisatierollen):
 * leden, rollen, uitnodigingen en het accepteren daarvan.
 */
final class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $company = $this->company($user);

        return view('dashboard.team', [
            'company' => $company,
            'members' => $company->users()->orderBy('name')->get(),
            'invitations' => $company->invitations()->open()->latest('id')->get(),
            'roles' => CompanyRole::cases(),
            'mayManage' => $user->canManageTeam(),
        ]);
    }

    public function invite(Request $request, TeamManager $team): RedirectResponse
    {
        $user = $request->user();
        $company = $this->company($user);
        abort_unless($user->canManageTeam(), 403);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(CompanyRole::class)],
        ]);

        $team->invite($company, $user, $validated['email'], CompanyRole::from($validated['role']));

        return redirect()->route('team.index')->with('status', __('team.invited', ['email' => $validated['email']]));
    }

    public function updateRole(Request $request, User $member, TeamManager $team): RedirectResponse
    {
        $user = $request->user();
        $company = $this->company($user);
        abort_unless($user->canManageTeam(), 403);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(CompanyRole::class)],
        ]);

        $team->changeRole($company, $user, $member, CompanyRole::from($validated['role']));

        return redirect()->route('team.index')->with('status', __('team.role_changed', ['name' => $member->name]));
    }

    public function remove(Request $request, User $member, TeamManager $team): RedirectResponse
    {
        $user = $request->user();
        $company = $this->company($user);
        abort_unless($user->canManageTeam(), 403);

        $team->remove($company, $user, $member);

        return redirect()->route('team.index')->with('status', __('team.removed', ['name' => $member->name]));
    }

    public function revoke(Request $request, CompanyInvitation $invitation, TeamManager $team): RedirectResponse
    {
        $user = $request->user();
        $company = $this->company($user);
        abort_unless($user->canManageTeam(), 403);

        $team->revoke($company, $user, $invitation);

        return redirect()->route('team.index')->with('status', __('team.revoked', ['email' => $invitation->email]));
    }

    /**
     * De pagina achter de maillink: wat je wordt uitgenodigd voor, en een
     * knop om het aan te nemen. Inloggen gaat eraan vooraf (auth-middleware).
     */
    public function showInvitation(Request $request, CompanyInvitation $invitation): View
    {
        return view('team.invitation', [
            'invitation' => $invitation->load(['company', 'inviter']),
            'user' => $request->user(),
            'open' => $invitation->isOpen(),
            'matches' => mb_strtolower($request->user()->email) === mb_strtolower($invitation->email),
        ]);
    }

    public function accept(Request $request, CompanyInvitation $invitation, TeamManager $team): RedirectResponse
    {
        $team->accept($invitation, $request->user());

        return redirect()->route('team.index')->with('status', __('team.joined', ['company' => $invitation->company->name]));
    }

    private function company(User $user): Company
    {
        $company = $user->company;

        abort_if($company === null, 404);

        return $company;
    }
}
