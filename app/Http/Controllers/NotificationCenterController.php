<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dashboard\ActivityFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Het meldingencentrum (briefing sectie 3): alles wat per mail of push is
 * gegaan, in-app terug te vinden in de lade die vanaf de bel uitschuift.
 * Openen markeert als gelezen en gaat door naar waar de melding over gaat.
 */
final class NotificationCenterController extends Controller
{
    /** Zoveel meldingen toont de lade eerst; de rest staat achter "Oudere meldingen". */
    private const int PANEL_LIMIT = 15;

    /** Zoveel meldingen gaat de lade hooguit terug. */
    private const int PANEL_MAX = 100;

    /**
     * Een eigen pagina met meldingen is er niet meer: de lade vanaf de bel is
     * de plek. Oude links (mail, bladwijzer, het menu op de site) komen op
     * het dashboard uit met de lade open.
     */
    public function index(): RedirectResponse
    {
        return redirect()->to($this->drawerUrl());
    }

    /**
     * Het paneel dat rechts uitschuift vanaf de bel: de laatste meldingen en
     * tickets, wat nog niet gelezen is bovenaan. Taken staan bij het team.
     * Met ?alles=1 komen ook de oudere meldingen mee.
     */
    public function panel(Request $request, ActivityFeed $feed): View
    {
        $user = $request->user();
        $all = $request->boolean('alles');
        $items = $feed->for($user, collect(), $all ? self::PANEL_MAX : self::PANEL_LIMIT * 2)['items'];

        return view('dashboard.partials.notifications-panel', [
            'items' => $all ? $items : array_slice($items, 0, self::PANEL_LIMIT),
            'hasMore' => ! $all && count($items) > self::PANEL_LIMIT,
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return redirect()->to(is_string($url) && $url !== '' ? $url : $this->drawerUrl());
    }

    /**
     * Het dashboard met de lade open (zie notifDrawer in dash-overlays.js).
     */
    private function drawerUrl(): string
    {
        return route('dashboard', ['meldingen' => 1]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Alle meldingen zijn als gelezen gemarkeerd.');
    }
}
