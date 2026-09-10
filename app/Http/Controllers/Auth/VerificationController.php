<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\KycStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * De verificatieroute (hfst. 13.6): e-mail → telefoon → identiteit → KVK
 * (alleen zakelijk). Nodig vóór het eerste bod, niet vóór het rondkijken.
 *
 * De identiteits- en KVK-stap zijn hier de plek waar straks de
 * verificatiepartner (hfst. 14) terugkeert met zijn uitkomst. Die partner is
 * een open beslissing (hfst. 18); tot die tijd keurt deze stub direct goed -
 * met precies de bewaarregel die dan ook geldt: alleen het resultaat en de
 * datum, nooit het documentbeeld of de selfie.
 */
final class VerificationController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('auth.verification', [
            'user' => $user,
            'company' => $user->company,
            'isBusiness' => $user->type === UserType::Business,
        ]);
    }

    /**
     * Telefoonnummer vastleggen. De sms-bevestiging hoort bij dezelfde
     * partnerkeuze als de identiteitscheck; tot die tijd is opgeven genoeg.
     */
    public function storePhone(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ]{8,20}$/'],
        ]);

        $user = $request->user();
        $user->phone = $validated['phone'];
        $user->phone_verified_at = now();
        $user->save();

        return redirect()->route('verification.overview');
    }

    /**
     * De identiteitscheck - pas ná een bevestigd e-mailadres, dat is de
     * volgorde van de checklist.
     */
    public function storeIdentity(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'identity' => __('verification.errors.email_first'),
            ]);
        }

        $user->kyc_status = KycStatus::Verified;
        $user->kyc_verified_at = now();
        $user->save();

        $user->refreshBidPermission();

        return redirect()->route('verification.overview');
    }

    /**
     * De KVK-koppeling voor zakelijke accounts. De controle op de
     * tenaamstelling doet straks de partner; het resultaat landt hier.
     */
    public function storeKvk(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->company;

        if ($user->type !== UserType::Business || $company === null) {
            throw ValidationException::withMessages([
                'kvk' => __('verification.errors.business_only'),
            ]);
        }

        $company->verified_at = now();
        $company->save();

        return redirect()->route('verification.overview');
    }
}
