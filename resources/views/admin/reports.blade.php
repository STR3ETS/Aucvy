<x-layouts.admin title="Rapportages" subtitle="Vaste rapporten als csv, en een wekelijkse of maandelijkse mail. Elke export staat in het inzagelog.">
    @if (session('status'))
        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">
            {{ session('status') }}
            @if (session('export_url')) <a href="{{ session('export_url') }}" class="font-bold underline">Download (csv)</a>@endif
        </p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <div class="mt-6 grid gap-5 lg:grid-cols-2 lg:items-start">
        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="maken">
            <h2 id="maken" class="text-sm font-extrabold">Rapport maken</h2>
            <form method="POST" action="{{ route('admin.reports.run') }}" class="mt-3 space-y-3 text-sm">
                @csrf
                <fieldset>
                    <legend class="font-bold">Welk rapport</legend>
                    <div class="mt-1 grid gap-1.5 sm:grid-cols-2">
                        @foreach ($reports as $key)
                            <label class="flex items-center gap-2 rounded-aj-sm border border-aj-border p-2"><input type="radio" name="report" value="{{ $key }}" @checked($loop->first)> {{ __('admin.reports.'.$key) }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="grid gap-1"><span class="font-bold">Van</span><input type="date" name="from" value="{{ $defaultFrom }}" required class="rounded-aj-sm border border-aj-border-strong px-2" style="min-height:38px"></label>
                    <label class="grid gap-1"><span class="font-bold">Tot en met</span><input type="date" name="to" value="{{ $defaultTo }}" required class="rounded-aj-sm border border-aj-border-strong px-2" style="min-height:38px"></label>
                </div>
                <button type="submit" class="btn btn-dark" style="height:40px;font-size:13px">Maak csv (link 24 uur geldig)</button>
            </form>
        </section>

        <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="planning">
            <h2 id="planning" class="text-sm font-extrabold">Vaste rapporten per mail</h2>
            <form method="POST" action="{{ route('admin.reports.schedule') }}" class="mt-3 space-y-2 text-sm">
                @csrf
                <select name="report" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:38px">
                    @foreach ($reports as $key)<option value="{{ $key }}">{{ __('admin.reports.'.$key) }}</option>@endforeach
                </select>
                <select name="frequency" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:38px"><option value="weekly">Wekelijks, over de vorige week</option><option value="monthly">Maandelijks, over de vorige maand</option></select>
                <input type="text" name="recipients" required placeholder="E-mailadressen, gescheiden door komma's" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:38px">
                <button type="submit" class="btn btn-dark" style="height:40px;font-size:13px">Inplannen</button>
            </form>
            <ul class="mt-4 divide-y divide-aj-border text-sm">
                @forelse ($schedules as $schedule)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span><span class="font-bold">{{ __('admin.reports.'.$schedule->report_key) }}</span> · {{ $schedule->frequency === 'monthly' ? 'maandelijks' : 'wekelijks' }}<span class="block text-xs text-aj-muted">{{ implode(', ', $schedule->recipients) }}{{ $schedule->last_sent_at ? ' · laatst '.$schedule->last_sent_at->diffForHumans() : '' }}</span></span>
                        <form method="POST" action="{{ route('admin.reports.schedule.destroy', $schedule) }}">@csrf @method('DELETE')<button type="submit" class="text-xs font-bold underline">Verwijderen</button></form>
                    </li>
                @empty
                    <li class="py-2 text-xs text-aj-muted">Nog geen rapporten ingepland.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.admin>
