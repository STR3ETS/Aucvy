<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Het meldingencentrum (briefing sectie 3): alles wat per mail of push is
 * gegaan, in-app terug te vinden. Openen markeert als gelezen en gaat door
 * naar waar de melding over gaat.
 */
final class NotificationCenterController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dashboard.notifications', [
            'notifications' => $user->notifications()->latest()->paginate(30),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return redirect()->to(is_string($url) && $url !== '' ? $url : route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Alle meldingen zijn als gelezen gemarkeerd.');
    }
}
