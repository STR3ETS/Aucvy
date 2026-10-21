<x-layouts.error code="419" title="Je sessie is verlopen"
    message="De pagina stond te lang open. Ga terug en probeer het opnieuw; wat je invulde is niet verzonden.">
    <a class="btn btn-primary" href="{{ url()->previous() }}">Terug en opnieuw proberen</a>
    <a class="btn btn-ghost" href="{{ url('/') }}">Naar de home</a>
</x-layouts.error>
