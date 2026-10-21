<x-layouts.dashboard title="Uitnodiging" eyebrow="Team" :intro="'Je bent uitgenodigd voor het team van '.$invitation->company->name.'.'">
    @if ($errors->any())
        <div role="alert" class="mb-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ $errors->first() }}</div>
    @endif

    <section class="mx-auto max-w-[560px] rounded-aj-s border border-aj-border bg-aj-surface p-6" aria-labelledby="uitnodiging">
        <h2 id="uitnodiging" class="text-lg font-extrabold">{{ $invitation->company->name }}</h2>
        <p class="mt-1 text-sm text-aj-muted">
            {{ $invitation->inviter?->name ?? 'Een collega' }} nodigt je uit als <b>{{ $invitation->role->label() }}</b>.
            {{ $invitation->role->description() }}
        </p>

        @if (! $open)
            <p class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ __('team.errors.expired') }}</p>
        @elseif (! $matches)
            <p class="mt-4 rounded-aj-sm border border-aj-warning/30 bg-aj-warning/5 p-3 text-sm">{{ __('team.errors.wrong_account', ['email' => $invitation->email]) }}</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="btn btn-ghost">Uitloggen en met een ander account inloggen</button>
            </form>
        @elseif ($user->company_id !== null && $user->company_id !== $invitation->company_id)
            <p class="mt-4 rounded-aj-sm border border-aj-error/30 bg-aj-error/5 p-3 text-sm text-aj-error">{{ __('team.errors.other_company_self') }}</p>
        @else
            <form method="POST" action="{{ route('team.accept', $invitation) }}" class="mt-5 flex flex-wrap gap-2">
                @csrf
                <button type="submit" class="btn btn-primary">Uitnodiging aannemen</button>
                <a class="btn btn-ghost" href="{{ route('dashboard') }}">Niet nu</a>
            </form>
            <p class="mt-3 text-xs text-aj-muted">Je account wordt een zakelijk account onder {{ $invitation->company->name }}. Je eigen biedhistorie blijft van jou.</p>
        @endif
    </section>
</x-layouts.dashboard>
