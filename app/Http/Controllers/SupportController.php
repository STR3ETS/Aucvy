<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Admin\Tickets;
use App\Models\Order;
use App\Notifications\SupportTicketForwarded;
use App\Notifications\SupportTicketReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/**
 * Het contactformulier (briefing sectie 1: support). Elk bericht wordt een
 * ticket voor de backoffice en gaat door naar de supportinbox; de afzender
 * krijgt een ontvangstbevestiging. Werkt met en zonder account.
 */
final class SupportController extends Controller
{
    public function create(Request $request): View
    {
        $user = $request->user();

        return view('contact', [
            'user' => $user,
            'orders' => $user === null ? collect() : Order::query()
                ->where(fn ($query) => $query->where('buyer_id', $user->id)->orWhere('seller_id', $user->id))
                ->latest('id')
                ->limit(10)
                ->get(['id', 'order_number', 'lot_id'])
                ->load('lot:id,title'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
            'order_number' => ['nullable', 'string', 'max:32'],
            // Honingpot: een bot vult dit veld; een mens ziet het niet.
            'website' => ['prohibited'],
        ]);

        $ticket = app(Tickets::class)->open([
            'user_id' => $request->user()?->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'order_number' => $validated['order_number'] ?? null,
            'source' => 'form',
            'ip' => $request->ip(),
        ]);

        Notification::route('mail', (string) config('aucvy.support.email'))->notify(new SupportTicketForwarded($ticket));
        Notification::route('mail', [$ticket->email => $ticket->name])->notify(new SupportTicketReceived($ticket));

        return redirect()->route('contact')->with('status', __('support.sent', ['id' => $ticket->id]));
    }
}
