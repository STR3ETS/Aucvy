<x-layouts.dashboard title="Team" eyebrow="Account" :intro="'Wie namens '.$company->name.' op Aucvy werkt, en wat elke rol mag.'">
    <x-slot:crumbs>
        <a href="{{ route('account.settings') }}">Account</a> <span aria-hidden="true">/</span> <span>Team</span>
    </x-slot:crumbs>

    @if (session('status'))
        <p class="mb-4 rounded-aj-sm border border-aj-success/30 bg-aj-success/5 p-3 text-sm text-aj-success-text">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 lg:grid-cols-[1fr_340px] lg:items-start">
        <div class="space-y-5">
            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="leden">
                <h2 id="leden" class="text-lg font-extrabold">Teamleden</h2>
                <p class="mt-1 text-sm text-aj-muted">{{ $members->count() }} {{ $members->count() === 1 ? 'persoon' : 'personen' }} bij {{ $company->name }}.</p>
                <ul class="mt-4 divide-y divide-aj-border">
                    @foreach ($members as $member)
                        <li class="flex flex-wrap items-center gap-3 py-3">
                            <span class="team-avatar">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                            <span class="min-w-0 flex-1">
                                <b class="block truncate text-sm">{{ $member->name }}@if ($member->is(auth()->user())) <span class="text-aj-muted">(jij)</span>@endif</b>
                                <span class="block truncate text-xs text-aj-muted">{{ $member->email }}</span>
                            </span>
                            @if ($mayManage && ! $member->is(auth()->user()))
                                <form method="POST" action="{{ route('team.role', $member) }}" class="flex items-center gap-2">
                                    @csrf @method('PATCH')
                                    <label class="sr-only" for="rol-{{ $member->id }}">Rol van {{ $member->name }}</label>
                                    <select id="rol-{{ $member->id }}" name="role" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2 text-sm" style="min-height:36px" onchange="this.form.requestSubmit()">
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->value }}" @selected($member->companyRole() === $role)>{{ $role->label() }}</option>
                                        @endforeach
                                    </select>
                                </form>
                                <form method="POST" action="{{ route('team.remove', $member) }}" onsubmit="return confirm('{{ $member->name }} uit het team halen?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-ghost" style="height:36px;font-size:13px">Verwijderen</button>
                                </form>
                            @else
                                <span class="rounded-full border border-aj-border px-3 py-1 text-xs font-bold">{{ $member->companyRole()?->label() ?? 'Lid' }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            @if ($invitations->isNotEmpty())
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="open">
                    <h2 id="open" class="text-sm font-extrabold">Open uitnodigingen</h2>
                    <ul class="mt-3 divide-y divide-aj-border text-sm">
                        @foreach ($invitations as $invitation)
                            <li class="flex flex-wrap items-center gap-3 py-2">
                                <span class="min-w-0 flex-1">
                                    <b class="block truncate">{{ $invitation->email }}</b>
                                    <span class="block text-xs text-aj-muted">{{ $invitation->role->label() }} · verloopt {{ $invitation->expires_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM') }}</span>
                                </span>
                                @if ($mayManage)
                                    <form method="POST" action="{{ route('team.revoke', $invitation) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-ghost" style="height:34px;font-size:12px">Intrekken</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="space-y-5">
            @if ($mayManage)
                <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="uitnodigen">
                    <h2 id="uitnodigen" class="text-sm font-extrabold">Collega uitnodigen</h2>
                    <p class="mt-1 text-xs text-aj-muted">Je collega krijgt een mail met een link die veertien dagen geldig is.</p>
                    <form method="POST" action="{{ route('team.invite') }}" class="mt-3 grid gap-3">
                        @csrf
                        <label class="grid gap-1 text-sm">
                            <span class="font-bold">E-mailadres</span>
                            <input type="email" name="email" required value="{{ old('email') }}" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm" style="min-height:40px" placeholder="collega@bedrijf.nl">
                        </label>
                        <label class="grid gap-1 text-sm">
                            <span class="font-bold">Rol</span>
                            <select name="role" class="rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2 text-sm" style="min-height:40px">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->value }}" @selected(old('role', 'editor') === $role->value)>{{ $role->label() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="btn btn-primary justify-center">Uitnodiging sturen</button>
                    </form>
                </section>
            @endif

            <section class="rounded-aj-s border border-aj-border bg-aj-surface p-5" aria-labelledby="rollen">
                <h2 id="rollen" class="text-sm font-extrabold">Wat elke rol mag</h2>
                <dl class="mt-3 space-y-3 text-sm">
                    @foreach ($roles as $role)
                        <div>
                            <dt class="font-bold">{{ $role->label() }}</dt>
                            <dd class="text-xs text-aj-muted">{{ $role->description() }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </aside>
    </div>
</x-layouts.dashboard>
