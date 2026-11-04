{{-- Gevuld door het verspreidingsblok (deelkit en kanalen); tot dan een nette lege staat. --}}
@php $distributions = $distributions ?? collect(); @endphp
@if ($distributions->isEmpty())
    <p class="mt-4 rounded-aj-sm border border-dashed border-aj-border-strong p-5 text-center text-sm text-aj-muted">Nog niet verspreid. Zodra de kavel live staat, kun je hem hier delen op je eigen kanalen.</p>
@else
    <ul class="mt-4 divide-y divide-aj-border rounded-aj-s border border-aj-border text-sm">
        @foreach ($distributions as $distribution)
            <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                <span class="font-bold">{{ $distribution->channelLabel() }}</span>
                <span class="text-xs text-aj-muted">{{ $distribution->statusLabel() }}@if ($distribution->clicks) · {{ $distribution->clicks }} klikken @endif</span>
            </li>
        @endforeach
    </ul>
@endif
