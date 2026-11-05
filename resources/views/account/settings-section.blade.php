@php
    // Eén onderdeel van de instellingen op een eigen pagina (ontwerp van november).
    [$heading, $intro] = [
        'gegevens' => ['Persoonlijke gegevens', 'Je naam, je biednaam en je telefoonnummer.'],
        'beveiliging' => ['Beveiliging', 'Je wachtwoord en de tweede inlogstap.'],
        'meldingen' => ['Meldingen', 'Welke meldingen en mails je van Aucvy krijgt.'],
        'apparaten' => ['Apparaten', 'Waar je bent ingelogd, en elders uitloggen.'],
    ][$section];
@endphp

<x-layouts.dashboard :title="$heading" eyebrow="Account" :intro="$intro">
    <x-slot:crumbs>
        <a href="{{ route('account.settings') }}">Instellingen</a> <span aria-hidden="true">/</span> <span>{{ $heading }}</span>
    </x-slot:crumbs>

    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div role="alert" class="d-soft is-err" style="margin:0 0 14px">{{ $errors->first() }}</div>
    @endif

    <div class="d-stack" style="gap:14px">
        @include('account.partials.'.$section)
    </div>
</x-layouts.dashboard>
