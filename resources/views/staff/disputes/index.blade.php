{{-- In de backoffice-layout: geschillen zijn beheerwerk, geen aparte omgeving. --}}
<x-layouts.admin title="Geschillen" subtitle="Open meldingen, gesorteerd op de reactietermijn van de verkoper.">
    <div>
        @if ($disputes->isEmpty())
            <p class="mt-6 text-sm text-aj-muted">Geen open geschillen.</p>
        @else
            <ul class="mt-6 space-y-2">
                @foreach ($disputes as $dispute)
                    <li>
                        <a href="{{ route('staff.disputes.show', $dispute) }}"
                           class="flex flex-wrap items-center justify-between gap-3 rounded-aj-sm border border-aj-border bg-aj-surface p-4 hover:border-aj-border-strong">
                            <span>
                                <span class="block text-sm font-bold">{{ $dispute->order->lot->title }}</span>
                                <span class="text-xs text-aj-muted">
                                    {{ $dispute->order->order_number }}
                                    · {{ __('orders.dispute_reasons.'.$dispute->reason) }}
                                    · {{ $dispute->status->label() }}
                                </span>
                            </span>
                            <span class="text-right text-xs text-aj-muted">
                                verkoper reageert vóór<br>
                                <time class="tabular font-bold text-aj-text" datetime="{{ $dispute->sla_due_at?->toIso8601String() }}">
                                    {{ $dispute->sla_due_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '-' }}
                                </time>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.admin>
