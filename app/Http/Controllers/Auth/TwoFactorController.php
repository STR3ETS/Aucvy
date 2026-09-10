<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Lots\DraftAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * De tweede inlogstap voor accounts met een gekoppelde uitbetaling
 * (hfst. 15): een zescijferige code per mail, tien minuten geldig. De
 * gebruiker is op dit punt nog níét ingelogd - dat gebeurt pas hier.
 */
final class TwoFactorController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('two_factor')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('two_factor');

        if ($pending === null) {
            return redirect()->route('login');
        }

        $request->validate(['code' => ['required', 'digits:6']]);

        $throttleKey = 'two-factor:'.$pending['id'];

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $hashed = Cache::get('two_factor_code:'.$pending['id']);

        if ($hashed === null || ! Hash::check($request->string('code')->value(), $hashed)) {
            RateLimiter::hit($throttleKey, 600);

            throw ValidationException::withMessages([
                'code' => 'Die code klopt niet of is verlopen.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        Cache::forget('two_factor_code:'.$pending['id']);
        $request->session()->forget('two_factor');

        Auth::loginUsingId($pending['id'], $pending['remember'] ?? false);
        $request->session()->regenerate();

        app(DraftAccess::class)->claimFor($request);

        return redirect()->intended(route('home'));
    }
}
