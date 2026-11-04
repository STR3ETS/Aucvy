<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * De factuur als afdrukbare pagina (opslaan als pdf via de browser).
 * Alleen beide partijen van de order en beheer.
 */
final class InvoiceController extends Controller
{
    public function show(Request $request, Invoice $invoice): View
    {
        abort_unless($invoice->isVisibleTo($request->user()), 404);

        $invoice->load(['order.lot', 'credits']);

        return view('invoices.show', ['invoice' => $invoice]);
    }
}
