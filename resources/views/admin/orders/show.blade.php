@use('App\Support\Money\Money')

<x-layouts.admin :title="'Order '.$order->order_number" :subtitle="$order->lot->title.' · '.$order->status->label()">
    <p class="text-sm text-aj-muted">
        <a href="{{ route('admin.orders') }}" class="underline">Orders</a> · {{ $order->order_number }}
    </p>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Bedragen">
            <h2 class="text-sm font-extrabold">Bedragen</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Hamerprijs</dt><dd class="tabular font-semibold">{{ Money::format($order->hammer_cents) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Opgeld</dt><dd class="tabular font-semibold">{{ Money::format($order->buyer_premium_cents) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Transport</dt><dd class="tabular font-semibold">{{ Money::format($order->transport_cents) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Btw</dt><dd class="tabular font-semibold">{{ Money::format($order->vat_cents) }}</dd></div>
                <div class="flex justify-between gap-3 border-t border-aj-border pt-2"><dt class="font-bold">Totaal</dt><dd class="tabular font-extrabold">{{ Money::format($order->total_cents) }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Btw-regime</dt><dd class="font-semibold">{{ $order->vat_regime?->label() ?? '-' }}</dd></div>
            </dl>
        </section>

        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Partijen">
            <h2 class="text-sm font-extrabold">Partijen</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Kavel</dt>
                    <dd class="max-w-[24ch] truncate font-semibold"><a href="{{ route('admin.lots.show', $order->lot) }}" class="text-aj-blue-text underline">{{ $order->lot->title }}</a></dd>
                </div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Koper</dt>
                    <dd class="font-semibold"><a href="{{ route('admin.users.show', $order->buyer) }}" class="text-aj-blue-text underline">{{ $order->buyer->name }}</a></dd>
                </div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Verkoper</dt>
                    <dd class="font-semibold"><a href="{{ route('admin.users.show', $order->seller) }}" class="text-aj-blue-text underline">{{ $order->seller->name }}</a></dd>
                </div>
                @foreach ($order->invoices as $invoice)
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">{{ $invoice->kindLabel() }}</dt><dd><a href="{{ route('invoices.show', $invoice) }}" class="font-semibold underline">{{ $invoice->number }}</a></dd></div>
                @endforeach
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Uitbetaling</dt>
                    <dd class="font-semibold">{{ $order->payout?->status->label() ?? 'nog niet aangemaakt' }}</dd>
                </div>
                @if ($order->payout)
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Uit te betalen</dt><dd class="tabular font-semibold">{{ Money::format($order->payout->amount_cents) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-aj-muted">Vrijgave</dt><dd class="font-semibold">{{ $order->payout->hold_until?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</dd></div>
                @endif
            </dl>
        </section>

        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Tijdlijn">
            <h2 class="text-sm font-extrabold">Tijdlijn</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Aangemaakt</dt><dd class="font-semibold">{{ $order->created_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Betaaldeadline</dt><dd class="font-semibold">{{ $order->payment_deadline_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Betaald</dt><dd class="font-semibold">{{ $order->paid_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Verzonden</dt><dd class="font-semibold">{{ $order->shipped_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Geleverd</dt><dd class="font-semibold">{{ $order->delivered_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Meldvenster tot</dt><dd class="font-semibold">{{ $order->dispute_window_ends_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-aj-muted">Afgerond</dt><dd class="font-semibold">{{ $order->completed_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</dd></div>
            </dl>
        </section>
    </div>

    <div class="mt-8">
        <x-orders.messages :order="$order" :messages="$order->messages" :action="route('admin.orders.messages.store', $order)" :viewer="auth()->user()" :staff="true" />
    </div>

    @if ($order->payments->isNotEmpty())
        <section class="mt-8" aria-label="Betalingen">
            <h2 class="text-lg font-extrabold">Betalingen</h2>
            <x-admin.table>
                <x-slot:head>
                    <th>Provider</th><th>Methode</th><th>Status</th><th class="text-right">Bedrag</th><th>Betaald op</th>
                </x-slot:head>
                @foreach ($order->payments as $payment)
                    <tr>
                        <td class="font-semibold">{{ $payment->provider }}</td>
                        <td>{{ $payment->method ?? '-' }}</td>
                        <td>{{ $payment->status->label() }}</td>
                        <td class="tabular text-right font-semibold">{{ Money::format($payment->amount_cents) }}</td>
                        <td class="whitespace-nowrap text-aj-muted">{{ $payment->paid_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}</td>
                    </tr>
                @endforeach
            </x-admin.table>
        </section>
    @endif

    @if ($order->disputes->isNotEmpty())
        <section class="mt-8" aria-label="Geschillen">
            <h2 class="text-lg font-extrabold">Geschillen</h2>
            <ul class="mt-2 space-y-2">
                @foreach ($order->disputes as $dispute)
                    <li>
                        <a href="{{ route('staff.disputes.show', $dispute) }}"
                           class="flex flex-wrap items-center justify-between gap-3 rounded-aj-sm border border-aj-border bg-aj-surface p-4 hover:border-aj-border-strong">
                            <span class="text-sm font-semibold">
                                {{ __('orders.dispute_reasons.'.$dispute->reason) }}
                                <span class="text-aj-muted">· gemeld door {{ $dispute->reporter->publicName() }}</span>
                            </span>
                            <span class="text-sm font-bold">{{ $dispute->status->label() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($order->review)
        <section class="mt-8 rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="Review">
            <h2 class="text-sm font-extrabold">Review van {{ $order->review->reviewer->publicName() }}</h2>
            <p class="mt-2 text-sm"><span class="tabular font-extrabold">{{ $order->review->rating }}/5</span>
                @if ($order->review->body) · {{ $order->review->body }} @endif
            </p>
        </section>
    @endif
</x-layouts.admin>
