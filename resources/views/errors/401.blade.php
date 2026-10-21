<x-layouts.error code="401" title="Log eerst in"
    message="Deze pagina is alleen voor ingelogde gebruikers.">
    <a class="btn btn-primary" href="{{ url('/inloggen') }}">Inloggen</a>
    <a class="btn btn-ghost" href="{{ url('/') }}">Naar de home</a>
</x-layouts.error>
