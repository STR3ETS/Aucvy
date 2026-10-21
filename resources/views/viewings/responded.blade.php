@use('App\Enums\ViewingStatus')

{{-- De landingspagina achter de maillinks van de verkoper. --}}
@php
    $heading = match ($viewing->status) {
        ViewingStatus::Confirmed => 'Bezichtiging bevestigd',
        ViewingStatus::Cancelled => 'Moment afgewezen',
        default => 'Deze aanvraag is al afgehandeld',
    };
@endphp

<x-layouts.public title="Bezichtiging" :heading="$heading" eyebrow="Bezichtiging" align="center">
    <div class="mx-auto max-w-md text-center">
        @if ($viewing->status === ViewingStatus::Confirmed)
            <p class="text-sm text-aj-soft">
                {{ $viewing->user->publicName() }} ontvangt het adres per mail voor
                {{ $viewing->slot_at->timezone(config('aucvy.display_timezone'))->isoFormat('dddd D MMMM, HH:mm') }}.
            </p>
        @elseif ($viewing->status === ViewingStatus::Cancelled)
            <p class="text-sm text-aj-soft">
                De aanvrager krijgt bericht en kan een nieuw moment voorstellen. Er is geen adres gedeeld.
            </p>
        @else
            <p class="text-sm text-aj-soft">Er is niets veranderd.</p>
        @endif

        <a href="{{ route('lots.show', $viewing->lot) }}" class="btn btn-primary mt-6">Naar de kavel</a>
    </div>
</x-layouts.public>
