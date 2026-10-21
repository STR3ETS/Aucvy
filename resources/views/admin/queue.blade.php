<x-layouts.admin title="Wachtrij" :subtitle="$failedTotal.' mislukt in totaal, '.$failedToday.' in de laatste 24 uur; '.$pending->sum('total').' in de wacht'">
    @if (session('status'))
        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif

    <div class="mt-6 grid gap-5 lg:grid-cols-[320px_1fr] lg:items-start">
        <div class="space-y-5">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="wacht">
                <h2 id="wacht" class="text-sm font-extrabold">In de wacht</h2>
                <p class="mt-1 text-xs text-aj-muted">Per queue; de worker draait <code>queue:work</code> op de database-driver.</p>
                @if ($pending->isEmpty())
                    <p class="mt-3 text-sm text-aj-muted">Niets in de wacht.</p>
                @else
                    <ul class="mt-3 divide-y divide-aj-border text-sm">
                        @foreach ($pending as $row)
                            <li class="flex items-center justify-between gap-3 py-2">
                                <span><b>{{ $row['queue'] }}</b>@if ($row['oldest'] !== null && $row['oldest'] < now()->subMinutes(5)->timestamp) <span class="text-aj-warn-text">· oudste wacht {{ \Carbon\Carbon::createFromTimestamp($row['oldest'])->diffForHumans(null, true) }}</span>@endif</span>
                                <span class="tabular font-bold">{{ $row['total'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="perjob">
                <h2 id="perjob" class="text-sm font-extrabold">Mislukt per job</h2>
                @if ($failedByJob->isEmpty())
                    <p class="mt-3 text-sm text-aj-muted">Geen mislukte jobs.</p>
                @else
                    <ul class="mt-3 divide-y divide-aj-border text-sm">
                        @foreach ($failedByJob as $row)
                            <li class="flex items-center justify-between gap-3 py-2">
                                <span class="min-w-0"><b class="block truncate">{{ class_basename($row['job']) }}</b><span class="block text-xs text-aj-muted">laatst {{ \Carbon\Carbon::parse($row['last'])->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</span></span>
                                <span class="tabular font-bold">{{ $row['total'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.queue.retry') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary" style="height:38px;font-size:13px">Alles opnieuw proberen</button>
                        </form>
                        <form method="POST" action="{{ route('admin.queue.flush') }}" onsubmit="return confirm('Alle {{ $failedTotal }} mislukte jobs weggooien?')">
                            @csrf
                            <button type="submit" class="btn btn-ghost" style="height:38px;font-size:13px">Alles opruimen</button>
                        </form>
                    </div>
                @endif
            </section>
        </div>

        <section class="overflow-x-auto rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="mislukt">
            <h2 id="mislukt" class="text-sm font-extrabold">Laatste mislukte jobs</h2>
            <p class="mt-1 text-xs text-aj-muted">De eerste regel van de fout; de volledige trace staat in het logbestand. Elke retry en opruimactie komt in het auditlog.</p>
            @if ($failed->isEmpty())
                <p class="mt-4 rounded-aj-s border border-dashed border-aj-border-strong p-6 text-center text-sm text-aj-muted">Niets mislukt. Zo hoort het.</p>
            @else
                <table class="admin-table mt-3" style="table-layout:fixed;width:100%">
                    <colgroup><col style="width:13%"><col style="width:20%"><col style="width:10%"><col><col style="width:17%"></colgroup>
                    <thead><tr><th>Wanneer</th><th>Job</th><th>Queue</th><th>Fout</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($failed as $row)
                            <tr>
                                <td class="whitespace-nowrap text-aj-muted">{{ \Carbon\Carbon::parse($row['failed_at'])->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</td>
                                <td class="truncate font-bold" title="{{ $row['job'] }}">{{ class_basename($row['job']) }}</td>
                                <td>{{ $row['queue'] }}</td>
                                <td class="text-xs text-aj-muted" style="white-space:normal"><span class="line-clamp-2" title="{{ $row['reason'] }}">{{ $row['reason'] }}</span></td>
                                <td>
                                    <div class="flex flex-col items-start gap-1">
                                        <form method="POST" action="{{ route('admin.queue.retry') }}">
                                            @csrf
                                            <input type="hidden" name="uuid" value="{{ $row['uuid'] }}">
                                            <button type="submit" class="btn btn-ghost" style="height:30px;padding:0 10px;font-size:12px">Opnieuw</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.queue.forget') }}">
                                            @csrf
                                            <input type="hidden" name="uuid" value="{{ $row['uuid'] }}">
                                            <button type="submit" class="btn btn-ghost" style="height:30px;padding:0 10px;font-size:12px">Verwijderen</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>
</x-layouts.admin>
