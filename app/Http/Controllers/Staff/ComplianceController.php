<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Compliance\Actions\BlockUser;
use App\Compliance\Actions\DecideKyb;
use App\Compliance\Actions\DecideKyc;
use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * De compliance-queue: identiteitscontroles en bedrijfscontroles die op een
 * besluit wachten, en de accounts die geblokkeerd zijn. Elk besluit loopt
 * door een Action, met audit en mail.
 */
final class ComplianceController extends Controller
{
    public function index(): View
    {
        return view('admin.compliance', [
            'identities' => User::query()
                ->where('kyc_status', KycStatus::Pending)
                ->with('company')
                ->oldest('kyc_requested_at')
                ->get(),
            'companies' => Company::query()
                ->whereNotNull('kyb_requested_at')
                ->whereNull('verified_at')
                ->with('users')
                ->oldest('kyb_requested_at')
                ->get(),
            'blocked' => User::query()
                ->whereNotNull('blocked_at')
                ->latest('blocked_at')
                ->get(),
            'rejected' => User::query()
                ->where('kyc_status', KycStatus::Rejected)
                ->latest('updated_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function decideKyc(Request $request, User $user, DecideKyc $decide): RedirectResponse
    {
        $validated = $this->decision($request);

        $decide->handle($user, $request->user(), $validated['decision'] === 'approve', $validated['note'] ?? null);

        return back()->with('status', __('compliance.kyc_'.$validated['decision'], ['name' => $user->name]));
    }

    public function decideKyb(Request $request, Company $company, DecideKyb $decide): RedirectResponse
    {
        $validated = $this->decision($request);

        $decide->handle($company, $request->user(), $validated['decision'] === 'approve', $validated['note'] ?? null);

        return back()->with('status', __('compliance.kyb_'.$validated['decision'], ['name' => $company->name]));
    }

    public function block(Request $request, User $user, BlockUser $blocker): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        abort_if($user->is_staff, 422, 'Een medewerker blokkeer je niet via deze knop.');

        $blocker->block($user, $request->user(), $validated['reason']);

        return back()->with('status', __('compliance.blocked', ['name' => $user->name]));
    }

    public function unblock(Request $request, User $user, BlockUser $blocker): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $blocker->unblock($user, $request->user(), $validated['note'] ?? null);

        return back()->with('status', __('compliance.unblocked', ['name' => $user->name]));
    }

    /**
     * Goedkeuren mag zonder toelichting; afwijzen niet, want de reden gaat
     * naar de aanvrager.
     *
     * @return array{decision: string, note?: string|null}
     */
    private function decision(Request $request): array
    {
        return $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['required_if:decision,reject', 'nullable', 'string', 'max:500'],
        ]);
    }
}
