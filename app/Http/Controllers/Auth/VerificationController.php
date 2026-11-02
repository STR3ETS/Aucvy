<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\KycStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Notifications\VerificationDecided;
use App\Support\Audit\Audit;
use App\Support\Sms\PhoneVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * De verificatieroute (hfst. 13.6): e-mail → telefoon → identiteit → KVK
 * (alleen zakelijk). Nodig vóór het eerste bod, niet vóór het rondkijken.
 *
 * De identiteits- en KVK-stap zijn hier de plek waar straks de
 * verificatiepartner (hfst. 14) terugkeert met zijn uitkomst. Die partner is
 * een open beslissing (hfst. 18); tot die tijd gaat een aanvraag naar de
 * compliance-queue in de backoffice en beslist een mens (flow E). Bewaard
 * wordt alleen het resultaat en de datum, nooit het documentbeeld of de
 * selfie. `aucvy.verification.auto_approve` is de kortsluiting voor lokaal.
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
     * Telefoonnummer vastleggen en meteen een code per sms sturen (besluit
     * Raph, 2 november 2026). Bevestigd is het pas met de juiste code.
     */
    public function storePhone(Request $request, PhoneVerification $verification): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $phone = PhoneVerification::normalise($validated['phone']);

        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('verification.phone.invalid_number')]);
        }

        $user = $request->user();

        if ($user->phone !== $phone || $user->phone_verified_at === null) {
            $user->phone = $phone;
            $user->phone_verified_at = null;
            $user->save();

            $verification->sendCode($user);
        }

        return redirect()->route('verification.overview')->with('status', __('verification.phone.sent', ['phone' => $phone]));
    }

    public function resendPhoneCode(Request $request, PhoneVerification $verification): RedirectResponse
    {
        $user = $request->user();

        if ($user->phone === null || $user->phone_verified_at !== null) {
            return redirect()->route('verification.overview');
        }

        $verification->sendCode($user);

        return redirect()->route('verification.overview')->with('status', __('verification.phone.sent', ['phone' => $user->phone]));
    }

    public function confirmPhone(Request $request, PhoneVerification $verification): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:10']]);

        $verification->confirm($request->user(), $validated['code']);

        return redirect()->route('verification.overview')->with('status', __('verification.phone.verified'));
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

        if (in_array($user->kyc_status, [KycStatus::Pending, KycStatus::Verified], true)) {
            return redirect()->route('verification.overview');
        }

        $before = $user->kyc_status;
        $approved = (bool) config('aucvy.verification.auto_approve');

        $user->kyc_status = $approved ? KycStatus::Verified : KycStatus::Pending;
        $user->kyc_requested_at = now();
        $user->kyc_verified_at = $approved ? now() : null;
        $user->kyc_note = null;
        $user->save();

        $user->refreshBidPermission();

        Audit::record('user.kyc_changed', $user, $user, ['status' => $before->value], [
            'status' => $user->kyc_status->value,
            'via' => $approved ? 'auto' : 'self',
        ]);

        if ($approved) {
            $user->notify(new VerificationDecided(KycStatus::Verified));
        }

        return redirect()->route('verification.overview');
    }

    /**
     * De UBO-verklaring (briefing sectie 2: KYB met KvK, UBO en IBAN): wie
     * 25% of meer houdt of de zeggenschap heeft; anders de bestuurders.
     * Compliance kijkt het na bij het KvK-besluit en bevestigt het mee.
     */
    public function storeUbo(Request $request): RedirectResponse
    {
        $user = $request->user();
        $company = $user->company;

        if ($user->type !== UserType::Business || $company === null) {
            throw ValidationException::withMessages([
                'ubos' => __('verification.errors.business_only'),
            ]);
        }

        abort_unless($user->canManageTeam(), 403);

        if ($company->isVerified()) {
            return redirect()->route('verification.overview');
        }

        $validated = $request->validate([
            'ubos' => ['required', 'array', 'min:1', 'max:10'],
            'ubos.*.name' => ['required', 'string', 'max:120'],
            'ubos.*.birth_year' => ['required', 'integer', 'between:1900,'.(now()->year - 16)],
            'ubos.*.role' => ['required', Rule::in(['ubo', 'director'])],
            'ubos.*.share' => ['nullable', 'integer', 'between:25,100', 'required_if:ubos.*.role,ubo'],
        ], [
            'ubos.required' => __('verification.ubo.errors.empty'),
            'ubos.*.birth_year.between' => __('verification.ubo.errors.birth_year'),
            'ubos.*.share.between' => __('verification.ubo.errors.share'),
            'ubos.*.share.required_if' => __('verification.ubo.errors.share'),
        ]);

        $declaration = collect($validated['ubos'])->map(fn (array $person): array => [
            'name' => trim($person['name']),
            'birth_year' => (int) $person['birth_year'],
            'role' => $person['role'],
            'share' => $person['role'] === 'ubo' ? (int) $person['share'] : null,
        ])->values()->all();

        $company->forceFill(['ubo_declaration' => $declaration, 'ubo_declared_at' => now(), 'ubo_verified_at' => null])->save();

        Audit::record('company.ubo_declared', $company, $user, after: ['count' => count($declaration)], ip: $request->ip());

        return redirect()->route('verification.overview')->with('status', __('verification.ubo.saved'));
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

        abort_unless($user->canManageTeam(), 403);

        if ($company->isVerified() || $company->awaitsVerification()) {
            return redirect()->route('verification.overview');
        }

        $approved = (bool) config('aucvy.verification.auto_approve');

        $company->kyb_requested_at = now();
        $company->verified_at = $approved ? now() : null;
        $company->kyb_note = null;
        $company->save();

        Audit::record($approved ? 'company.kyb_verified' : 'company.kyb_requested', $company, $user, after: [
            'kvk_number' => $company->kvk_number,
            'via' => $approved ? 'auto' : 'self',
        ]);

        return redirect()->route('verification.overview');
    }
}
