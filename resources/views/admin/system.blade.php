@php
    $tone = fn (string $status): string => match ($status) {
        'ok' => 'border-aj-success/40 bg-aj-success/5 text-aj-success-text',
        'warn' => 'border-aj-warn/40 bg-aj-warn/10 text-aj-warn-text',
        default => 'border-aj-error/40 bg-aj-error/5 text-aj-error',
    };
    $word = fn (string $status): string => match ($status) { 'ok' => 'In orde', 'warn' => 'Let op', default => 'Fout' };
@endphp

<x-layouts.admin title="Systeem" :subtitle="'Zelfde controles als php artisan system:health in de cron; mail naar support bij een fout, hooguit één keer per uur per controle. Stand: '.$word($overall)">
    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($checks as $check)
            <div class="rounded-aj-s border bg-aj-surface p-4 {{ $check['status'] === 'ok' ? 'border-aj-border' : ($check['status'] === 'warn' ? 'border-aj-warn/40' : 'border-aj-error/40') }}">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-bold text-aj-muted">{{ $check['label'] }}</p>
                    <span class="rounded-full border px-2 py-0.5 text-xs font-bold {{ $tone($check['status']) }}">{{ $word($check['status']) }}</span>
                </div>
                <p class="mt-2 text-sm font-semibold">{{ $check['value'] }}</p>
                @if ($check['hint'])
                    <p class="mt-1 text-xs text-aj-muted">{{ $check['hint'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-[1fr_320px] lg:items-start">
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="fouten">
            <h2 id="fouten" class="text-sm font-extrabold">Laatste fouten uit het log</h2>
            <p class="mt-1 text-xs text-aj-muted">De eerste regel per fout, nieuwste eerst; de volledige trace staat in storage/logs.</p>
            @if ($errors === [])
                <p class="mt-4 rounded-aj-s border border-dashed border-aj-border-strong p-6 text-center text-sm text-aj-muted">Geen fouten in de staart van het log.</p>
            @else
                <ol class="mt-3 divide-y divide-aj-border text-sm">
                    @foreach ($errors as $error)
                        <li class="py-2">
                            <span class="text-xs text-aj-muted">{{ $error['at'] }} · {{ $error['level'] }}</span>
                            <span class="block break-words font-mono text-xs">{{ $error['message'] }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <aside class="space-y-5">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="feiten">
                <h2 id="feiten" class="text-sm font-extrabold">Instellingen</h2>
                <dl class="mt-2 space-y-1 text-sm">
                    @foreach ($facts as $label => $value)
                        <div class="flex justify-between gap-3"><dt class="text-aj-muted">{{ $label }}</dt><dd class="font-semibold">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </section>
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="cron">
                <h2 id="cron" class="text-sm font-extrabold">Op de server</h2>
                <p class="mt-1 text-xs text-aj-muted">Twee processen horen altijd te draaien; de hartslagen hierboven laten zien of dat zo is.</p>
                <pre class="mt-2 overflow-x-auto rounded-aj-sm bg-aj-bg p-3 text-xs">* * * * * php artisan schedule:run
php artisan queue:work database --queue=realtime,ai,default --tries=3</pre>
                <p class="mt-2 text-xs text-aj-muted">Hartslagen: de scheduler schrijft elke minuut een tijdstip; elke queue krijgt elke minuut een lege job die de worker aftekent.</p>
            </section>
        </aside>
    </div>
</x-layouts.admin>
