<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Live\LiveSessions;
use App\Models\LiveSession;
use App\Models\Lot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * De studio van de verkoper (/dashboard/kavels/{id}/live): camera- en
 * verbindingscheck, aftelling, live, kijkersaantal, stoppen. Browser op
 * telefoon of laptop, geen app.
 */
final class LiveStudioController extends Controller
{
    public function __construct(private readonly LiveSessions $sessions) {}

    public function show(Request $request, Lot $lot): View
    {
        abort_unless(LiveSessions::enabled() && $lot->isManagedBy($request->user()), 404);

        $lot->load(['auction', 'company']);
        $session = LiveSession::query()->where('lot_id', $lot->id)->open()->latest('id')->first();

        return view('dashboard.live-studio', [
            'lot' => $lot,
            'session' => $session,
            'blocker' => $session === null ? $this->sessions->canStart($lot, $request->user()) : null,
            'token' => $session !== null ? $this->sessions->publisherToken($session, $request->user()) : null,
            'clientUrl' => $this->sessions->clientUrl(),
            'clientScript' => (string) config('aucvy.live.client_script'),
            'history' => LiveSession::query()->where('lot_id', $lot->id)->whereIn('status', [LiveSession::ENDED, LiveSession::FAILED])->latest('id')->limit(5)->get(),
        ]);
    }

    public function start(Request $request, Lot $lot): RedirectResponse
    {
        abort_unless(LiveSessions::enabled() && $lot->isManagedBy($request->user()), 404);

        $this->sessions->start($lot->load('auction'), $request->user());

        return redirect()->route('dashboard.lots.live', $lot)->with('status', 'Kamer klaar. Doe de camera- en verbindingscheck, dan de aftelling.');
    }

    /**
     * Na de check en de aftelling meldt de studio zich: nu echt live.
     */
    public function goLive(Request $request, Lot $lot, LiveSession $session): JsonResponse
    {
        abort_unless($session->lot_id === $lot->id && $lot->isManagedBy($request->user()), 404);

        $this->sessions->goLive($session);

        return response()->json(['status' => $session->fresh()->status]);
    }

    public function end(Request $request, Lot $lot, LiveSession $session): RedirectResponse
    {
        abort_unless($session->lot_id === $lot->id && $lot->isManagedBy($request->user()), 404);

        $this->sessions->end($session);

        return redirect()->route('dashboard.lots.live', $lot)->with('status', 'Uitzending gestopt. De veiling loopt gewoon door.');
    }
}
