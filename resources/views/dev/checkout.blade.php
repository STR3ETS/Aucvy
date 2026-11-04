<x-layouts.public title="Nepcheckout" :hero="false">
    {{--
        De checkout van de FakePaymentProvider - bestaat alleen met de
        nepprovider actief. De knoppen doen wat Mollie zou doen: uitkomst
        vastleggen en de webhookverwerking aanroepen.
    --}}
    <div class="mx-auto max-w-md">
        <div class="d-card">
            <p class="text-xs font-extrabold uppercase tracking-wide text-aj-soft">Nepcheckout (lokaal)</p>
            <h1 class="mt-1 text-xl font-extrabold">{{ $payment->order->order_number }}</h1>
            <p class="tabular mt-2 text-3xl font-extrabold">
                {{ \App\Support\Money\Money::format($payment->amount_cents) }}
            </p>
            <p class="mt-1 text-sm text-aj-muted">Zo zou hier iDEAL staan. Kies de uitkomst die je wilt testen.</p>

            <form method="POST" action="{{ route('dev.checkout.store', $payment) }}" class="mt-6 flex flex-col gap-2">
                @csrf
                <button type="submit" name="outcome" value="paid" class="btn btn-primary">Betaal {{ \App\Support\Money\Money::format($payment->amount_cents) }}</button>
                <button type="submit" name="outcome" value="failed" class="btn btn-ghost">Laat mislukken</button>
                <button type="submit" name="outcome" value="expired" class="btn btn-ghost">Laat verlopen</button>
            </form>
        </div>
    </div>
</x-layouts.public>
