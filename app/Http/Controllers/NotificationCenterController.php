<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dashboard\ActivityFeed;
use App\Models\AiInsight;
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
    /**
     * Dezelfde lijst als op het dashboard (taken, meldingen en tickets), maar
     * dan de laatste honderd in plaats van een greep.
     */
    public function index(Request $request, ActivityFeed $feed): View
    {
        $user = $request->user();

        $tasks = AiInsight::query()
            ->where('user_id', $user->id)
            ->current()
            ->orderByRaw("field(severity, 'urgent', 'attention', 'info')")
            ->get()
            ->filter(fn (AiInsight $insight): bool => $insight->hasAction());

        return view('dashboard.notifications', [
            'feed' => $feed->for($user, $tasks, 100),
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
