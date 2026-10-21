<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Iban;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Organisatie-instellingen (briefing sectie 4): bedrijfsgegevens, pakket,
 * verificatiestatus en de uitbetaalrekening. Iedereen in het bedrijf kijkt
 * mee, eigenaar en beheerder wijzigen, alleen de eigenaar raakt de rekening.
 */
final class OrganisationController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $company = $this->company($user);

        return view('dashboard.organisation', [
            'company' => $company,
            'members' => $company->users()->count(),
            'mayEdit' => $user->canManageTeam(),
            'mayEditPayout' => $user->companyRole() === CompanyRole::Owner,
            'nameLocked' => $company->isVerified() || $company->awaitsVerification(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $this->company($user);
        abort_unless($user->canManageTeam(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'vat_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z]{2}[A-Za-z0-9 .]{2,17}$/'],
        ], [
            'vat_number.regex' => 'Een btw-nummer begint met de landcode, bijvoorbeeld NL123456789B01.',
        ]);

        $before = ['name' => $company->name, 'vat_number' => $company->vat_number];

        // De naam is gekoppeld aan de KvK-controle: na de aanvraag staat hij vast.
        if (! $company->isVerified() && ! $company->awaitsVerification()) {
            $company->name = $validated['name'];
        }

        $company->vat_number = isset($validated['vat_number']) ? strtoupper(str_replace([' ', '.'], '', $validated['vat_number'])) : null;
        $company->save();

        Audit::record('company.updated', $company, $user, $before, ['name' => $company->name, 'vat_number' => $company->vat_number], $request->ip());

        return redirect()->route('organisation.show')->with('status', 'De bedrijfsgegevens zijn opgeslagen.');
    }

    public function updatePayout(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $this->company($user);
        abort_unless($user->companyRole() === CompanyRole::Owner, 403);

        // De bestemming van het geld: een gekaapte of achtergebleven sessie
        // mag die niet kunnen omzetten zonder het wachtwoord opnieuw.
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'iban' => ['required', 'string', 'max:40', fn (string $attribute, mixed $value, Closure $fail) => Iban::isValid((string) $value) || $fail('Dit is geen geldig IBAN. Controleer de cijfers.')],
            'iban_name' => ['required', 'string', 'max:120'],
        ]);

        $before = ['payout_iban_last4' => $company->payout_iban_last4, 'payout_iban_name' => $company->payout_iban_name];

        // Alleen de laatste vier cijfers en de tenaamstelling; het volledige
        // nummer hoort bij de betaalprovider (migratie companies).
        $company->payout_iban_last4 = Iban::last4($validated['iban']);
        $company->payout_iban_name = trim($validated['iban_name']);
        $company->save();

        Audit::record('company.payout_changed', $company, $user, $before, ['payout_iban_last4' => $company->payout_iban_last4, 'payout_iban_name' => $company->payout_iban_name], $request->ip());

        return redirect()->route('organisation.show')->with('status', 'De uitbetaalrekening is vastgelegd. Iedereen van '.$company->name.' logt vanaf nu in met een code per mail.');
    }

    private function company(User $user): Company
    {
        $company = $user->company;

        abort_if($company === null, 404);

        return $company;
    }
}
