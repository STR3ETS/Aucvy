<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Lots\DraftAccess;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\TwoFactor\Authenticator;
use App\Support\TwoFactor\MailCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * De tweede inlogstap (hfst. 15). De gebruiker is op dit punt nog níét
 * ingelogd; dat gebeurt pas hier.
 *
 * Drie manieren, in deze volgorde:
 *  - de code uit de authenticator-app, als die gekoppeld is (besluit Raph,
 *    2 november 2026);
 *  - een code per mail, standaard zonder app en als terugval met app;
 *  - een herstelcode, voor wie zijn telefoon kwijt is. Elke herstelcode
 *    werkt één keer.
 */
final class TwoFactorController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get('two_factor');

        if ($pending === null) {
            return redirect()->route('login');
        }

        return view('auth.two-factor', [
            'method' => $pending['method'] ?? 'mail',
            'hasApp' => User::query()->find($pending['id'])?->hasAuthenticatorApp() ?? false,
        ]);
    }

    public function store(Request $request, Authenticator $authenticator, MailCode $mailCode): RedirectResponse
    {
        $pending = $request->session()->get('two_factor');

        if ($pending === null) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'max:12'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:24'],
        ]);

        $throttleKey = 'two-factor:'.$pending['id'];

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $user = User::query()->findOrFail($pending['id']);
        $method = $pending['method'] ?? 'mail';

        $passed = match (true) {
            $request->filled('recovery_code') => $user->hasAuthenticatorApp() && $authenticator->useRecoveryCode($user, $request->string('recovery_code')->value()),
            $method === 'app' => $authenticator->verify($user, $request->string('code')->value()),
            default => $mailCode->check($user, $request->string('code')->value()),
        };

        if (! $passed) {
            RateLimiter::hit($throttleKey, 600);

            throw ValidationException::withMessages([
                'code' => $request->filled('recovery_code') ? __('two_factor.errors.recovery_invalid') : __('two_factor.errors.code_invalid'),
            ]);
        }

        if ($request->filled('recovery_code')) {
            Audit::record('user.two_factor_recovery_used', $user, $user, after: ['remaining' => $authenticator->remainingRecoveryCodes($user->fresh())], ip: $request->ip());
        }

        RateLimiter::clear($throttleKey);
        $request->session()->forget('two_factor');

        // Geen "ingelogd blijven" voor wie een code moet geven: die cookie
        // zou de volgende keer zonder code inloggen (hfst. 15).
        Auth::loginUsingId($user->id, false);
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        $request->session()->regenerate();

        app(DraftAccess::class)->claimFor($request);

        return redirect()->intended(route('home'));
    }

    /**
     * Telefoon niet bij de hand: dan toch een code per mail. Vanaf hier geldt
     * de mailcode voor deze inlogpoging.
     */
    public function sendMail(Request $request, MailCode $mailCode): RedirectResponse
    {
        $pending = $request->session()->get('two_factor');

        if ($pending === null) {
            return redirect()->route('login');
        }

        $user = User::query()->findOrFail($pending['id']);
        $mailCode->send($user);

        $request->session()->put('two_factor', ['id' => $user->id, 'method' => 'mail']);

        return redirect()->route('two-factor.challenge')->with('status', __('two_factor.mail_sent'));
    }
}
