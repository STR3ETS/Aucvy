<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Orders\Actions\ProcessPaymentWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * De webhook van de betaalprovider (hfst. 14).
 *
 * Mollie stuurt alleen een `id` en verwacht een 200; de echte status wordt in
 * de actie bij de provider opgehaald. Onbekend id: 404, dan probeert Mollie
 * het later opnieuw - dat vangt de race af waarin de webhook eerder aankomt
 * dan onze eigen databasecommit.
 */
final class MollieWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessPaymentWebhook $webhook): Response
    {
        $providerPaymentId = (string) $request->input('id', '');

        abort_if($providerPaymentId === '', 422);

        $payment = $webhook->handle($providerPaymentId, $request->all());

        abort_if($payment === null, 404);

        return response('OK');
    }
}
