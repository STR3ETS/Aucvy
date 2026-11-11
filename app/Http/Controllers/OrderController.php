<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Lots\Dossier\Dossier;
use App\Models\Order;
use App\Orders\OrderTimeline;
use App\Support\Money\OrderCosts;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OrderController extends Controller
{
    /**
     * Bestellingen van de ingelogde gebruiker - als koper en als verkoper.
     * De volwaardige dashboards komen in stap 11; dit is de plek waar je
     * vandaag je order terugvindt.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('orders.index', [
            'buying' => Order::query()
                ->where('buyer_id', $user->id)
                ->with(['lot.media', 'invoices'])
                ->latest('id')
                ->get(),
            'selling' => Order::query()
                ->where('seller_id', $user->id)
                ->with(['lot.media', 'payout', 'invoices'])
                ->latest('id')
                ->get(),
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $user = $request->user();

        // Alleen de twee partijen. 404 en geen 403: dat een ordernummer
        // bestaat gaat een derde niets aan (zelfde regel als bij concepten).
        abort_unless(in_array($user->id, [$order->buyer_id, $order->seller_id], true), 404);

        $order->loadMissing(['lot.media', 'payout', 'disputes', 'payments', 'messages.author', 'invoices']);

        return view('orders.show', [
            'order' => $order,
            'costs' => OrderCosts::fromOrder($order),
            'isBuyer' => $user->id === $order->buyer_id,
            'openDispute' => $order->disputes->first(fn ($dispute) => $dispute->status->isOpen()),
            'timeline' => app(OrderTimeline::class)->for($order),
            'dossier' => Dossier::timeline($order->lot, $user->id === $order->buyer_id ? 'buyer' : 'seller', $user, orderId: $order->id, limit: 50),
        ]);
    }
}
