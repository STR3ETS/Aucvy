<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Orders\Actions\PostOrderMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Berichten bij een bestelling (briefing sectie 7). Koper en verkoper
 * schrijven vanaf de bestelpagina, support vanuit de backoffice.
 */
final class OrderMessageController extends Controller
{
    public function store(Request $request, Order $order, PostOrderMessage $post): RedirectResponse
    {
        $user = $request->user();

        abort_unless(in_array($user->id, [$order->buyer_id, $order->seller_id], true), 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:'.OrderMessage::MAX_LENGTH],
        ]);

        $post->handle($order, $user, $validated['body'], $request->ip());

        return redirect()->to(route('orders.show', $order).'#berichten')->with('status', 'Je bericht is verstuurd.');
    }

    public function storeAsStaff(Request $request, Order $order, PostOrderMessage $post): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:'.OrderMessage::MAX_LENGTH],
        ]);

        $post->handle($order, $request->user(), $validated['body'], $request->ip());

        return redirect()->to(route('admin.orders.show', $order).'#berichten')->with('status', 'Bericht verstuurd naar koper en verkoper.');
    }
}
