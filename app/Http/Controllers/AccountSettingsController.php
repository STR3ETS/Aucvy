<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\TwoFactor\Authenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Profiel en instellingen (briefing sectie 2): gegevens, wachtwoord,
 * meldingsvoorkeuren, en de sessies en apparaten van dit account.
 */
final class AccountSettingsController extends Controller
{
    /** @var list<string> De voorkeuren die een gebruiker zelf kan zetten. */
    public const PREFERENCES = ['outbid_push', 'outbid_email', 'alerts_email', 'watchlist_push', 'watchlist_email'];

    public function show(Request $request): View
    {
        $user = $request->user();
        $authenticator = app(Authenticator::class);

        // Bezig met koppelen: de QR en het geheim uit de sessie, nog niet op de gebruiker.
        $setupSecret = $request->session()->has('two_factor_setup')
            ? Crypt::decryptString($request->session()->get('two_factor_setup'))
            : null;

        return view('account.settings', [
            'user' => $user,
            'twoFactor' => [
                'active' => $user->hasAuthenticatorApp(),
                'setup_secret' => $setupSecret,
                'setup_qr' => $setupSecret === null ? null : $authenticator->qrSvg($user, $setupSecret),
                'recovery_left' => $authenticator->remainingRecoveryCodes($user),
            ],
            'preferences' => collect(self::PREFERENCES)->mapWithKeys(fn (string $key): array => [$key => $user->prefers($key)])->all(),
            'sessions' => $this->sessionsFor($user, $request->session()->getId()),
            'hasPush' => $user->pushSubscriptions()->exists(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'display_name' => ['nullable', 'string', 'min:3', 'max:40', 'regex:/^[\pL\pN .\-]+$/u'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ]{8,20}$/'],
        ]);

        $user = $request->user();

        // De biednaam: nooit een e-mailadres of telefoonnummer, en niet leeg (dan het nummer van Aucvy).
        $user->display_name = filled($validated['display_name'] ?? null) ? trim($validated['display_name']) : null;

        // Een ander nummer is een nieuw nummer: de bevestiging vervalt.
        if (($validated['phone'] ?? null) !== $user->phone) {
            $user->phone_verified_at = null;
        }

        $user->name = $validated['name'];
        $user->phone = $validated['phone'] ?? null;
        $user->save();

        return back()->with('status', 'Je gegevens zijn opgeslagen.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->password = $validated['password'];
        $user->save();

        // Een nieuw wachtwoord betekent: alleen dít apparaat blijft ingelogd.
        $user->revokeOtherSessions($request->session()->getId());
        $this->renewRecaller($request);

        Audit::record('user.password_changed', $user, $user, ip: $request->ip());

        return back()->with('status', 'Je wachtwoord is gewijzigd.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = $request->user();
        $prefs = [];

        foreach (self::PREFERENCES as $key) {
            $prefs[$key] = $request->boolean($key);
        }

        $user->notification_prefs = $prefs;
        $user->save();

        return back()->with('status', 'Je meldingsvoorkeuren zijn opgeslagen.');
    }

    /**
     * Alle andere sessies beëindigen (briefing sectie 2: sessies en
     * apparaten). Vraagt het wachtwoord, zodat een open laptop dit niet kan.
     */
    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        Auth::logoutOtherDevices($request->input('password'));

        // Ook de herinneringscookies van die apparaten: anders logt het
        // tweede apparaat zichzelf bij het volgende verzoek weer in.
        $request->user()->revokeOtherSessions($request->session()->getId());
        $this->renewRecaller($request);

        Audit::record('user.sessions_revoked', $request->user(), $request->user(), ip: $request->ip());

        return back()->with('status', 'Je bent op alle andere apparaten uitgelogd.');
    }

    /**
     * Was dit apparaat zelf met een herinneringscookie ingelogd, dan krijgt
     * het een nieuwe met de gewisselde token; anders zou het bij het
     * volgende bezoek ook uitgelogd zijn.
     */
    private function renewRecaller(Request $request): void
    {
        if ($request->cookies->has(Auth::guard()->getRecallerName())) {
            Auth::login($request->user(), remember: true);
        }
    }

    /**
     * De sessies uit de databasedriver; met een andere driver is alleen de
     * huidige bekend.
     *
     * @return list<array{current: bool, ip: string|null, agent: string, last_active: Carbon}>
     */
    private function sessionsFor(User $user, string $currentId): array
    {
        if (config('session.driver') !== 'database') {
            return [['current' => true, 'ip' => request()->ip(), 'agent' => $this->describeAgent((string) request()->userAgent()), 'last_active' => now()]];
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): array => [
                'current' => $session->id === $currentId,
                'ip' => $session->ip_address,
                'agent' => $this->describeAgent((string) $session->user_agent),
                'last_active' => now()->setTimestamp((int) $session->last_activity),
            ])
            ->values()
            ->all();
    }

    /**
     * Een user agent in gewone woorden: browser en apparaat, meer niet.
     */
    private function describeAgent(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            $agent === '' => 'Onbekende browser',
            default => 'Browser',
        };

        $device = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $device === null ? $browser : "{$browser} op {$device}";
    }
}
