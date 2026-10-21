<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Lots\DraftAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Ingelogd blijven" mag de tweede factor niet omzeilen.
 *
 * De herinneringscookie logt iemand in zonder wachtwoord én zonder code.
 * Voor wie een code moet geven (beheer, bedrijven met een uitbetaalrekening)
 * geven we daarom geen cookie meer uit; en wie er nog een heeft van vóór de
 * IBAN, of van vóór deze regel, wordt hier alsnog uitgelogd en opnieuw
 * langs de code gestuurd. Uitloggen wisselt de herinneringstoken, dus de
 * cookie is daarna op elk apparaat waardeloos (hfst. 15).
 */
final class EnsureSecondFactorOnRecall
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null && Auth::viaRemember() && $user->requiresSecondFactor()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Cookie::queue(Cookie::forget(DraftAccess::COOKIE));

            return redirect()
                ->route('login')
                ->with('status', __('auth.second_factor_required'));
        }

        return $next($request);
    }
}
