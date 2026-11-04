<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Transacties (control center): alle bestellingen met status, fee en een
 * link naar het dossier.
 */
final class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        return view('admin.transactions', [
            'status' => $status,
            'statuses' => OrderStatus::cases(),
            'orders' => Order::query()
                ->with(['lot', 'buyer', 'seller.company', 'payout', 'disputes'])
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->when($request->query('q'), fn ($query, $term) => $query->where(fn ($inner) => $inner
                    ->where('order_number', 'like', "%{$term}%")
                    ->orWhereHas('lot', fn ($lot) => $lot->where('title', 'like', "%{$term}%"))))
                ->latest('id')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }
}
