<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Lots\DraftAccess;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Inloggen, meer niet.
 *
 * Registratie, wachtwoordherstel en de verificatieroute horen bij stap 12
 * (Vertrouwen). Dit bestaat nu zodat er iemand kán bieden in de browser - de
 * biedmotor eist een ingelogde, geverifieerde gebruiker en dat is terecht.
 */
final class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->guardAgainstBruteForce($request);

        /*
         | Eerst alleen controleren, nog niet inloggen: wie een code moet geven
         | is pas ingelogd ná die code, en een inlog-plus-uitlog zou de
         | herinneringstoken van al zijn andere apparaten wissen.
         */
        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey($request));

            // Bewust niet verklappen of het e-mailadres bestaat.
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        /** @var User $user */
        $user = Auth::getProvider()->retrieveByCredentials($credentials);

        /*
         | Tweede factor bij een gekoppelde uitbetaling (hfst. 15): nog niet
         | ingelogd, code per mail, en verder bij /inloggen/code.
         */
        if ($user->requiresSecondFactor()) {
            $request->session()->put('two_factor', ['id' => $user->id]);

            $code = (string) random_int(100_000, 999_999);
            Cache::put('two_factor_code:'.$user->id, Hash::make($code), now()->addMinutes(10));
            $user->notify(new TwoFactorCode($code));

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $request->boolean('remember'));

        // Voorkomt session fixation: de sessie krijgt een nieuw id.
        $request->session()->regenerate();

        // Concepten uit deze browser gaan mee op naam: wie in de funnel begon
        // en pas bij het publiceren inlogt, raakt zijn werk niet kwijt.
        app(DraftAccess::class)->claimFor($request);

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // De conceptcookie hoort bij de persoon, niet bij de browser.
        Cookie::queue(Cookie::forget(DraftAccess::COOKIE));

        return redirect()->route('home');
    }

    /**
     * Vijf pogingen per minuut per e-mailadres en IP samen (hfst. 15).
     */
    private function guardAgainstBruteForce(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), maxAttempts: 5)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($this->throttleKey($request)),
            ]),
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return 'login|'.mb_strtolower((string) $request->input('email')).'|'.$request->ip();
    }
}
