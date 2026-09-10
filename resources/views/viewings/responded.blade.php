@use('App\Enums\ViewingStatus')

{{-- De landingspagina achter de maillinks van de verkoper. --}}
<x-layouts.public title="Bezichtiging">
    <div class="mx-auto max-w-md text-center">
        @if ($viewing->status === ViewingStatus::Confirmed)
            <h1 class="text-2xl font-extrabold tracking-tight">Bezichtiging bevestigd</h1>
            <p class="mt-3 text-sm text-aj-soft">
                {{ $viewing->user->publicName() }} ontvangt het adres per mail voor
                {{ $viewing->slot_at->timezone(config('aucvy.display_timezone'))->isoFormat('dddd D MMMM, HH:mm') }}.
            </p>
        @elseif ($viewing->status === ViewingStatus::Cancelled)
            <h1 class="text-2xl font-extrabold tracking-tight">Moment afgewezen</h1>
            <p class="mt-3 text-sm text-aj-soft">
                De aanvrager krijgt bericht en kan een nieuw moment voorstellen. Er is geen adres gedeeld.
            </p>
        @else
            <h1 class="text-2xl font-extrabold tracking-tight">Deze aanvraag is al afgehandeld</h1>
            <p class="mt-3 text-sm text-aj-soft">Er is niets veranderd.</p>
        @endif

        <a href="{{ route('lots.show', $viewing->lot) }}" class="btn btn-primary mt-6">Naar de kavel</a>
    </div>
</x-layouts.public>
