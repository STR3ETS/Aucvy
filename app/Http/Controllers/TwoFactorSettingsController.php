<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Audit\Audit;
use App\Support\TwoFactor\Authenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * De authenticator-app koppelen en beheren (besluit Raph, 2 november 2026).
 *
 * Alles hier vraagt eerst het wachtwoord: wie een open sessie vindt, mag
 * geen eigen app koppelen of de bestaande eraf halen. Het geheim staat
 * tijdens het koppelen alleen versleuteld in de sessie en komt pas op de
 * gebruiker als de eerste code klopt.
 */
final class TwoFactorSettingsController extends Controller
{
    private const SESSION_KEY = 'two_factor_setup';

    public function start(Request $request, Authenticator $authenticator): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        if ($request->user()->hasAuthenticatorApp()) {
            return redirect()->route('account.settings');
        }

        $request->session()->put(self::SESSION_KEY, Crypt::encryptString($authenticator->newSecret()));

        return redirect()->to(route('account.settings').'#tweestaps');
    }

    public function confirm(Request $request, Authenticator $authenticator): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);

        $encrypted = $request->session()->get(self::SESSION_KEY);

        if ($encrypted === null) {
            return redirect()->route('account.settings');
        }

        $secret = Crypt::decryptString($encrypted);

        if (! $authenticator->verifySecret($secret, $request->string('code')->value())) {
            throw ValidationException::withMessages(['code' => __('two_factor.errors.code_invalid')]);
        }

        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => null,
        ])->save();

        $codes = $authenticator->newRecoveryCodes($user);
        $request->session()->forget(self::SESSION_KEY);

        Audit::record('user.two_factor_enabled', $user, $user, ip: $request->ip());

        return redirect()->to(route('account.settings').'#tweestaps')
            ->with('status', __('two_factor.enabled'))
            ->with('recovery_codes', $codes);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('account.settings');
    }

    public function regenerateRecoveryCodes(Request $request, Authenticator $authenticator): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();

        if (! $user->hasAuthenticatorApp()) {
            return redirect()->route('account.settings');
        }

        $codes = $authenticator->newRecoveryCodes($user);

        Audit::record('user.two_factor_recovery_regenerated', $user, $user, ip: $request->ip());

        return redirect()->to(route('account.settings').'#tweestaps')
            ->with('status', __('two_factor.recovery_regenerated'))
            ->with('recovery_codes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();

        Audit::record('user.two_factor_disabled', $user, $user, ip: $request->ip());

        return redirect()->to(route('account.settings').'#tweestaps')->with('status', __('two_factor.disabled'));
    }
}
