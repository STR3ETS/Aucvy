<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($stats as [$label, $value, $url])
        <a href="{{ $url }}" class="rounded-aj-s border border-aj-border bg-aj-surface p-4 hover:border-aj-border-strong">
            <p class="text-xs font-bold text-aj-muted">{{ $label }}</p>
            <p class="tabular mt-1 text-2xl font-extrabold">{{ $value }}</p>
        </a>
    @endforeach
</div>

<section class="mt-8" aria-label="Live activiteit">
    <div class="flex items-center gap-2">
        <h2 class="text-lg font-extrabold">Live activiteit</h2>
        <span class="relative flex size-2" aria-hidden="true">
            <span class="absolute inline-flex size-full animate-ping rounded-full bg-aj-success opacity-60"></span>
            <span class="relative inline-flex size-2 rounded-full bg-aj-success"></span>
        </span>
    </div>

    @if ($feed->isEmpty())
        <p class="mt-2 text-sm text-aj-muted">Nog geen activiteit.</p>
    @else
        <ol class="mt-3 divide-y divide-aj-border rounded-aj-s border border-aj-border bg-aj-surface">
            @foreach ($feed as $event)
                <li>
                    <a href="{{ $event['url'] }}" class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 px-4 py-3 text-sm hover:bg-aj-blue/5">
                        <span><span class="font-bold">{{ $event['name'] }}</span> {{ $event['text'] }}</span>
                        <time class="whitespace-nowrap text-xs text-aj-muted" datetime="{{ $event['at']->toIso8601String() }}">
                            {{ $event['at']->diffForHumans(short: true) }}
                        </time>
                    </a>
                </li>
            @endforeach
        </ol>
    @endif
</section>
