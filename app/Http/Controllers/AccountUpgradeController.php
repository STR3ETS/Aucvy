<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Enums\UserType;
use App\Models\Company;
use App\Support\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Eén account voor kopen en verkopen: een particulier wordt hier zakelijk,
 * met hetzelfde account, dezelfde biedingen, bestellingen en volglijst. Er
 * komt een bedrijf bij met de gebruiker als eigenaar; daarna volgt de
 * KvK-controle (KYB) voordat er gepubliceerd kan worden.
 */
final class AccountUpgradeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->isBusiness()) {
            return redirect()->route('verification.overview');
        }

        return view('dashboard.business-upgrade');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isBusiness()) {
            return redirect()->route('verification.overview');
        }

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'kvk_number' => ['required', 'string', 'max:20'],
        ]);

        DB::transaction(function () use ($user, $validated): void {
            $company = Company::create([
                'name' => $validated['company_name'],
                'slug' => Str::slug($validated['company_name']).'-'.Str::lower(Str::random(6)),
                'kvk_number' => $validated['kvk_number'],
            ]);

            $user->forceFill([
                'type' => UserType::Business,
                'company_id' => $company->id,
                'company_role' => CompanyRole::Owner,
            ])->save();

            Audit::record('account.upgraded_to_business', $user, $user, after: ['company_id' => $company->id]);
        });

        return redirect()->route('verification.overview')
            ->with('status', 'Je account is nu zakelijk. Rond de KvK-controle af, dan kun je publiceren. Kopen en bieden gaat gewoon door.');
    }
}
