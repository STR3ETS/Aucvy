@props([
    // Collection van App\Models\LotActivity, nieuwste eerst.
    'activities',
    // Tonen wie het deed (beheer en verkoper), of alleen wat er gebeurde.
    'showActor' => true,
    'empty' => 'Nog niets gebeurd.',
])

@php
    $icons = [
        'lot' => 'M4 5.5A1.5 1.5 0 0 1 5.5 4h9A1.5 1.5 0 0 1 16 5.5v9a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 4 14.5z',
        'auction' => 'M11 3l6 6-3 3-6-6zM8 9l-4.5 4.5a1.5 1.5 0 0 0 2.1 2.1L10 11M12 15h5',
        'order' => 'M3 5h2l1.5 8h8l1.5-5.5H6.5M8 17a1 1 0 1 0 0 .01M14 17a1 1 0 1 0 0 .01',
        'message' => 'M4 5h12v8H9l-4 3v-3H4z',
        'mail' => 'M3 5h14v10H3zM3 6l7 5 7-5',
        'document' => 'M11.5 2.5h-6a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-10m-4-4 4 4m-4-4v4h4',
        'distribution' => 'M10 3v10M6 7l4-4 4 4M4 14v2a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-2',
        'live' => 'M3 6h10v8H3zM13 9l4-2v6l-4-2',
    ];
    $tones = [
        'lot' => 'bg-aj-blue/10 text-aj-blue-text',
        'auction' => 'bg-aj-warn/15 text-aj-warn-text',
        'order' => 'bg-aj-success/10 text-aj-success-text',
        'message' => 'bg-aj-navy/10 text-aj-navy',
        'mail' => 'bg-aj-bg text-aj-muted',
        'document' => 'bg-aj-bg text-aj-soft',
        'distribution' => 'bg-aj-blue/10 text-aj-blue-text',
        'live' => 'bg-aj-error/10 text-aj-error',
    ];
@endphp

@if ($activities->isEmpty())
    <p {{ $attributes->merge(['class' => 'rounded-aj-sm border border-dashed border-aj-border-strong bg-aj-surface p-5 text-center text-sm text-aj-muted']) }}>{{ $empty }}</p>
@else
    <ol {{ $attributes->merge(['class' => 'divide-y divide-aj-border rounded-aj-s border border-aj-border bg-aj-surface']) }} aria-label="Dossier">
        @foreach ($activities as $activity)
            @php $payload = $activity->payload ?? []; @endphp
            <li class="flex gap-3 px-4 py-3 text-sm">
                <span class="mt-0.5 grid size-7 flex-none place-items-center rounded-full {{ $tones[$activity->source] ?? 'bg-aj-bg text-aj-muted' }}" aria-hidden="true">
                    <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icons[$activity->source] ?? $icons['lot'] }}"/></svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold">{{ $activity->label() }}</span>
                    @if (! empty($payload['issues']))
                        <ul class="mt-1 list-disc pl-4 text-xs text-aj-soft">
                            @foreach ($payload['issues'] as $issue)<li>{{ $issue }}</li>@endforeach
                        </ul>
                    @endif
                    @if (! empty($payload['reason']))
                        <span class="block text-xs text-aj-soft">{{ $payload['reason'] }}</span>
                    @endif
                    @if (! empty($payload['note']))
                        <span class="block text-xs text-aj-soft">{{ $payload['note'] }}</span>
                    @endif
                    @if (! empty($payload['snippet']))
                        <span class="block truncate text-xs text-aj-soft">{{ $payload['snippet'] }}</span>
                    @endif
                    <span class="mt-0.5 block text-xs text-aj-muted">
                        <time datetime="{{ $activity->occurred_at->toIso8601String() }}">{{ $activity->occurred_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM YYYY, HH:mm') }}</time>
                        @if ($showActor)
                            · {{ match ($activity->actor_type) {
                                'ivy' => 'Ivy',
                                'staff' => 'Aucvy'.($activity->actor ? ' ('.$activity->actor->name.')' : ''),
                                'user' => $activity->actor?->name ?? 'gebruiker',
                                default => 'systeem',
                            } }}
                        @endif
                        · {{ __('dossier.sources.'.$activity->source) }}
                    </span>
                </span>
            </li>
        @endforeach
    </ol>
@endif
