@props(['filters'])

{{--
    "Zet een alert" op de zoekpagina (briefing sectie 3): de huidige filters
    als bewaarde zoekopdracht. Geen registratiemuur: wie niet is ingelogd
    gaat naar de login en komt hier terug.
--}}
@if ($filters->hasAny())
    @auth
        <form method="POST" action="{{ route('dashboard.alerts.store') }}" {{ $attributes }}>
            @csrf
            @foreach ($filters->toQuery() as $key => $value)
                @foreach ((array) $value as $item)
                    <input type="hidden" name="query[{{ $key }}]{{ is_array($value) ? '[]' : '' }}" value="{{ $item }}">
                @endforeach
            @endforeach
            <button type="submit" class="btn btn-ghost" style="height:40px;font-size:13px">
                <x-v4.icon name="bell" :size="15" stroke="2.2" /> Alert instellen
            </button>
        </form>
    @else
        <a href="{{ route('login') }}" class="btn btn-ghost" style="height:40px;font-size:13px" {{ $attributes }}>
            <x-v4.icon name="bell" :size="15" stroke="2.2" /> Alert instellen
        </a>
    @endauth
@endif
