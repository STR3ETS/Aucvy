<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Lots\DraftAccess;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Registreren: privé of zakelijk (hfst. 13.6).
 *
 * Het account is meteen bruikbaar om rond te kijken en te volgen; bieden
 * vraagt eerst de verificatieroute. Een zakelijk account krijgt direct zijn
 * bedrijf eronder - de KVK-controle op tenaamstelling komt in de
 * verificatiestap, niet hier.
 */
final class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, DraftAccess $drafts): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'type' => ['required', Rule::enum(UserType::class)],
            'company_name' => ['required_if:type,business', 'nullable', 'string', 'max:120'],
            'kvk_number' => ['required_if:type,business', 'nullable', 'string', 'max:20'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $company = null;

            if ($validated['type'] === UserType::Business->value) {
                $company = Company::create([
                    'name' => $validated['company_name'],
                    'slug' => Str::slug($validated['company_name']).'-'.Str::lower(Str::random(6)),
                    'kvk_number' => $validated['kvk_number'],
                ]);
            }

            return User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'type' => $validated['type'],
                'company_id' => $company?->id,
            ]);
        });

        // Stuurt de bevestigingsmail (MustVerifyEmail).
        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        // Wie in de funnel begon en zich hier pas registreert, houdt zijn werk.
        $drafts->claimFor($request);

        return redirect()->route('verification.overview');
    }
}
