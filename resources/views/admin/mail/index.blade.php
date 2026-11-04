@use('App\Models\MailAutomation')
@use('App\Models\MailCampaign')

<x-layouts.admin title="Mail Builder" subtitle="46 mails in één vast format, de acht reeksen en campagnes. Service is niet af te melden; activatie en campagne wel, met een maximum per week.">
    @if (session('status'))
        <p class="mt-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <div class="mt-6 grid gap-5 lg:grid-cols-[1fr_360px] lg:items-start">
        <div class="space-y-5">
            @foreach ($groups as $group => $templates)
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-label="{{ $group }}">
                    <h2 class="text-sm font-extrabold">{{ $group }}</h2>
                    <div class="mt-2 overflow-x-auto">
                        <table class="admin-table">
                            <thead><tr><th>#</th><th>Mail</th><th>Trigger</th><th>Soort</th><th>Versie</th><th>30 dagen</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($templates as $template)
                                    @php $stat = $stats->get($template->key); @endphp
                                    <tr>
                                        <td class="tabular text-aj-muted">{{ $template->number }}</td>
                                        <td><a href="{{ route('admin.mail.edit', $template) }}" class="font-semibold underline">{{ $template->name }}</a><span class="block text-xs text-aj-muted">{{ $template->currentVersion?->subject }}</span></td>
                                        <td class="text-xs">{{ $template->trigger }}@if ($template->notification) <span class="block text-aj-muted">via {{ $template->notification }}</span>@endif</td>
                                        <td><span @class(['rounded-full px-2 py-0.5 text-xs font-bold', 'bg-aj-bg' => $template->stream === 'service', 'bg-aj-warn/15 text-aj-warn-text' => $template->stream === 'activation', 'bg-aj-blue/10 text-aj-blue-text' => $template->stream === 'campaign'])>{{ $template->streamLabel() }}</span></td>
                                        <td class="tabular text-xs">v{{ $template->currentVersion?->version }}</td>
                                        <td class="tabular text-xs">{{ $stat ? $stat->sent.' verstuurd · '.$stat->clicked.' geklikt' : '-' }}</td>
                                        <td><a href="{{ route('admin.mail.edit', $template) }}" class="btn btn-ghost" style="height:30px;font-size:12px">Bewerken</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>

        <aside class="space-y-5">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="reeksen">
                <h2 id="reeksen" class="text-sm font-extrabold">Reeksen</h2>
                <p class="text-xs text-aj-muted">Elke reeks heeft een doel en stopt zodra dat is bereikt. Afmelden stopt lopende marketingreeksen.</p>
                <ul class="mt-3 divide-y divide-aj-border text-sm">
                    @foreach ($automations as $automation)
                        <li class="py-2">
                            <div class="flex items-start justify-between gap-2">
                                <span><span class="font-bold">{{ $automation->number }}. {{ $automation->name }}</span><span class="block text-xs text-aj-muted">{{ $automation->description }}</span></span>
                                <span class="shrink-0 text-right text-xs">
                                    <span @class(['rounded-full px-2 py-0.5 font-bold', 'bg-aj-success/10 text-aj-success-text' => $automation->status === MailAutomation::ACTIVE, 'bg-aj-bg' => $automation->status === MailAutomation::BUILT_IN, 'bg-aj-warn/15 text-aj-warn-text' => $automation->status === MailAutomation::PAUSED])>{{ $automation->statusLabel() }}</span>
                                    @if ($automation->status !== MailAutomation::BUILT_IN)
                                        <span class="tabular block text-aj-muted">{{ $automation->running_count }} lopend · {{ $automation->runs_count }} totaal</span>
                                        <form method="POST" action="{{ route('admin.mail.automations.toggle', $automation) }}">@csrf<button type="submit" class="font-semibold underline">{{ $automation->status === MailAutomation::ACTIVE ? 'Pauzeren' : 'Activeren' }}</button></form>
                                    @endif
                                </span>
                            </div>
                            <p class="mt-1 text-[11px] text-aj-muted">{{ collect($automation->steps)->map(fn ($step) => $step['type'] === 'wait' ? 'wacht '.$step['hours'].' u' : ($step['type'] === 'mail' ? 'mail '.$step['template'] : $step['type']))->implode(' · ') }}</p>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="campagnes">
                <h2 id="campagnes" class="text-sm font-extrabold">Campagnes</h2>
                <form method="POST" action="{{ route('admin.mail.campaigns') }}" class="mt-2 space-y-2 text-sm">
                    @csrf
                    <input type="text" name="name" required maxlength="120" placeholder="Naam van de campagne" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:36px">
                    <select name="template_id" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:36px">
                        @foreach ($campaignTemplates as $template)<option value="{{ $template->id }}">{{ $template->number }}. {{ $template->name }}</option>@endforeach
                    </select>
                    <select name="audience" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:36px">
                        <option value="all">Iedereen met toestemming</option><option value="buyers">Kopers</option><option value="sellers">Verkopers</option><option value="category">Volgers van een categorie</option>
                    </select>
                    <select name="category_id" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:36px">
                        <option value="">Geen categorie</option>
                        @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                    </select>
                    <input type="text" name="title" maxlength="200" placeholder="Titel (nieuwsbrief)" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:36px">
                    <input type="url" name="link" placeholder="Link naar het artikel (mag leeg)" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:36px">
                    <input type="datetime-local" name="scheduled_at" class="w-full rounded-aj-sm border border-aj-border-strong px-2" style="min-height:36px">
                    <button type="submit" class="btn btn-dark w-full justify-center" style="height:38px;font-size:13px">Inplannen</button>
                </form>
                <ul class="mt-3 divide-y divide-aj-border text-sm">
                    @forelse ($campaigns as $campaign)
                        <li class="flex items-center justify-between gap-2 py-2">
                            <span><span class="font-bold">{{ $campaign->name }}</span><span class="block text-xs text-aj-muted">{{ $campaign->version->template->name }} · {{ $campaign->audienceLabel() }} · {{ $campaign->scheduled_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') }}</span></span>
                            <span class="text-right text-xs">{{ $campaign->statusLabel() }}@if ($campaign->status === MailCampaign::SENT) <span class="tabular block text-aj-muted">{{ $campaign->sent_count }} verstuurd · {{ $campaign->skipped_count }} overgeslagen</span>@endif
                                @if ($campaign->status === MailCampaign::SCHEDULED)<form method="POST" action="{{ route('admin.mail.campaigns.cancel', $campaign) }}">@csrf<button type="submit" class="font-semibold underline">Annuleren</button></form>@endif</span>
                        </li>
                    @empty
                        <li class="py-2 text-xs text-aj-muted">Nog geen campagnes.</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>
</x-layouts.admin>
