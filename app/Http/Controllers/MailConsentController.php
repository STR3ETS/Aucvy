<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Mail\Automations\AutomationEngine;
use App\Models\MailConsent;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Afmelden met één klik (de koppen List-Unsubscribe) of via de link in de
 * voet. Afmelden stopt ook lopende reeksen. Servicemails blijven komen;
 * die zijn nodig voor account en transactie.
 */
final class MailConsentController extends Controller
{
    public function show(Request $request, User $user, string $stream): View
    {
        abort_unless($request->hasValidSignature() && in_array($stream, MailTemplate::MARKETING, true), 403);

        return view('mail.unsubscribe', [
            'user' => $user,
            'stream' => $stream,
            'done' => MailConsent::statusFor($user, $stream) === MailConsent::REVOKED,
            'url' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, User $user, string $stream): Response|RedirectResponse
    {
        abort_unless($request->hasValidSignature() && in_array($stream, MailTemplate::MARKETING, true), 403);

        MailConsent::set($user, $stream, MailConsent::REVOKED, $request->has('List-Unsubscribe') ? 'one_click' : 'link');

        if ($stream === MailTemplate::CAMPAIGN) {
            MailConsent::set($user, MailTemplate::ACTIVATION, MailConsent::REVOKED, 'link');
        }

        AutomationEngine::stopMarketingFor($user);

        if ($request->has('List-Unsubscribe') || $request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->to($request->fullUrl())->with('status', 'Je bent afgemeld.');
    }

    /**
     * Toestemming vanuit de accountinstellingen: campagnes aan of uit.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $granted = $request->boolean('campaign');

        MailConsent::set($user, MailTemplate::CAMPAIGN, $granted ? MailConsent::GRANTED : MailConsent::REVOKED, 'account');
        MailConsent::set($user, MailTemplate::ACTIVATION, $request->boolean('activation', true) ? MailConsent::GRANTED : MailConsent::REVOKED, 'account');

        if (! $request->boolean('activation', true)) {
            AutomationEngine::stopMarketingFor($user, 'opted_out');
        }

        return back()->with('status', 'Je mailvoorkeuren zijn opgeslagen.');
    }
}
