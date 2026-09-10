@props([
    'title',
    'body' => null,
])

{{--
    Een lege staat met precies één voorgestelde vervolgstap (hfst. 10).
    Nooit een doodlopende pagina.
--}}
<div {{ $attributes->merge(['class' => 'rounded-aj-sm border border-dashed border-aj-border-strong bg-aj-surface p-6 text-center']) }}>
    <p class="font-semibold">{{ $title }}</p>

    @if ($body)
        <p class="mt-1 text-sm text-aj-muted">{{ $body }}</p>
    @endif

    @if (isset($action))
        <div class="mt-4 flex justify-center">{{ $action }}</div>
    @endif
</div>
