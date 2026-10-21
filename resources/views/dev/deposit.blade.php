<x-layouts.public title="Borg reserveren" :hero="false">
    {{--
        De nepreservering van de FakePaymentProvider - bestaat alleen lokaal.
        Bij de echte provider wordt dit de pre-autorisatie van Mollie
        (hfst. 9 en 18).
    --}}
    <div class="mx-auto max-w-md">
        <div class="rounded-aj-s border border-aj-border bg-aj-surface p-6">
            <p class="text-xs font-extrabold uppercase tracking-wide text-aj-soft">Nepreservering (lokaal)</p>
            <h1 class="mt-1 text-xl font-extrabold">Borg voor "{{ $deposit->auction->lot->title }}"</h1>
            <p class="tabular mt-2 text-3xl font-extrabold">
                {{ \App\Support\Money\Money::format($deposit->amount_cents) }}
            </p>
            <p class="mt-1 text-sm text-aj-muted">
                Dit bedrag wordt gereserveerd, niet afgeschreven. Win je niet, dan komt het
                direct vrij; win je wel, dan komt het vrij zodra je betaling binnen is.
            </p>

            <form method="POST" action="{{ route('dev.deposit.store', $deposit) }}" class="mt-6">
                @csrf
                <button type="submit" class="btn btn-primary w-full justify-center">
                    Reserveer {{ \App\Support\Money\Money::format($deposit->amount_cents) }}
                </button>
            </form>
        </div>
    </div>
</x-layouts.public>
