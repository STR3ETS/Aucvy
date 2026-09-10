<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Orders\Actions\ReportProblem;
use App\Orders\Actions\StartPayment;
use App\Orders\Actions\TransitionOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * De handelingen op een order, elk een dunne schil om een actie.
 *
 * De controllers valideren wie er drukt; wat er mág gebeuren beslist de
 * statusmachine in TransitionOrder - hier staat geen enkele statuslogica.
 */
final class OrderActionController extends Controller
{
    /** De koper wil betalen: door naar de checkout van de provider. */
    public function pay(Request $request, Order $order, StartPayment $startPayment): RedirectResponse
    {
        $this->authorizeParticipant($request, $order);

        return redirect()->away($startPayment->handle($order, $request->user()));
    }

    /** De verkoper meldt: onderweg, of klaar om op te halen. */
    public function ship(Request $request, Order $order, TransitionOrder $transition): RedirectResponse
    {
        $this->authorizeParticipant($request, $order);

        if ($request->user()->id !== $order->seller_id) {
            throw ValidationException::withMessages(['order' => __('orders.errors.not_yours')]);
        }

        $validated = $request->validate([
            'method' => ['required', Rule::in(['pickup', 'shipped'])],
        ]);

        $transition->handle(
            $order,
            $validated['method'] === 'pickup' ? OrderStatus::ReadyForPickup : OrderStatus::Shipped,
            $request->user(),
        );

        return redirect()->route('orders.show', $order);
    }

    /** De koper bevestigt de levering - of dat gebeurt vanzelf na 7 dagen. */
    public function confirmDelivery(Request $request, Order $order, TransitionOrder $transition): RedirectResponse
    {
        $this->authorizeParticipant($request, $order);

        if ($request->user()->id !== $order->buyer_id) {
            throw ValidationException::withMessages(['order' => __('orders.errors.not_yours')]);
        }

        $transition->handle($order, OrderStatus::Delivered, $request->user());

        return redirect()->route('orders.show', $order);
    }

    /** De koper meldt een probleem; de uitbetaling staat per direct stil. */
    public function reportProblem(Request $request, Order $order, ReportProblem $reportProblem): RedirectResponse
    {
        $this->authorizeParticipant($request, $order);

        $validated = $request->validate([
            'reason' => ['required', Rule::in(array_keys(__('orders.dispute_reasons')))],
            'description' => ['required', 'string', 'max:4000'],
        ]);

        $reportProblem->handle($order, $request->user(), $validated['reason'], $validated['description']);

        return redirect()->route('orders.show', $order);
    }

    private function authorizeParticipant(Request $request, Order $order): void
    {
        abort_unless(
            in_array($request->user()->id, [$order->buyer_id, $order->seller_id], true),
            404,
        );
    }
}
