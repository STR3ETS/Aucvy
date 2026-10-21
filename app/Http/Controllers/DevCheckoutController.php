<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DepositStatus;
use App\Enums\PaymentStatus;
use App\Models\Deposit;
use App\Models\Payment;
use App\Orders\Actions\ProcessPaymentWebhook;
use App\Orders\Contracts\PaymentProvider;
use App\Orders\Providers\FakePaymentProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * De nepcheckout van de FakePaymentProvider.
 *
 * Bestaat alleen zolang de nepprovider actief is - met Mollie geconfigureerd
 * is dit een 404, dus in productie verdwijnt hij vanzelf mét de provider.
 * De knop doet precies wat Mollie zou doen: uitkomst vastleggen en daarna de
 * webhook afvuren. Zo test je de hele keten, inclusief idempotentie.
 */
final class DevCheckoutController extends Controller
{
    public function show(Request $request, Payment $payment): View
    {
        $this->guardFakeProvider();
        $this->guardOwner($request, $payment->order->buyer_id);

        return view('dev.checkout', ['payment' => $payment->load('order')]);
    }

    public function store(
        Request $request,
        Payment $payment,
        PaymentProvider $provider,
        ProcessPaymentWebhook $webhook,
    ): RedirectResponse {
        $this->guardFakeProvider();
        $this->guardOwner($request, $payment->order->buyer_id);

        $validated = $request->validate([
            'outcome' => ['required', Rule::in(['paid', 'failed', 'expired'])],
        ]);

        /** @var FakePaymentProvider $provider */
        $provider->pretend(
            (string) $payment->provider_payment_id,
            PaymentStatus::from($validated['outcome']),
        );

        // Daarna exact wat Mollie zou doen: de webhookverwerking aanroepen -
        // geen kortere weg langs de statusmachine.
        $webhook->handle((string) $payment->provider_payment_id, ['id' => $payment->provider_payment_id]);

        return redirect()->route('orders.show', $payment->order);
    }

    /** De nepreservering voor de borg - zelfde patroon als de betaling. */
    public function showDeposit(Request $request, Deposit $deposit): View
    {
        $this->guardFakeProvider();
        $this->guardOwner($request, $deposit->user_id);

        return view('dev.deposit', ['deposit' => $deposit->load('auction.lot')]);
    }

    public function storeDeposit(Request $request, Deposit $deposit): RedirectResponse
    {
        $this->guardFakeProvider();
        $this->guardOwner($request, $deposit->user_id);

        $deposit->status = DepositStatus::Authorised;
        $deposit->provider_id = 'fake_'.Str::random(16);
        $deposit->save();

        return redirect()->route('lots.show', $deposit->auction->lot);
    }

    private function guardFakeProvider(): void
    {
        abort_unless(config('aucvy.payments.provider') === 'fake', 404);
    }

    /**
     * Ook een nepcheckout is van één persoon: andermans betaling afronden of
     * andermans borg autoriseren hoort op geen enkele omgeving te kunnen.
     */
    private function guardOwner(Request $request, int $ownerId): void
    {
        $user = $request->user();

        abort_unless($user !== null && ($user->id === $ownerId || $user->is_staff), 403);
    }
}
