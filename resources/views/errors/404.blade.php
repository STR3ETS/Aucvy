<x-layouts.error code="404" title="Pagina niet gevonden"
    message="Deze pagina bestaat niet meer, of de link klopt niet helemaal. Kavels die zijn gesloten blijven wel te vinden via de veilingen.">
    <a class="btn btn-primary" href="{{ url('/veilingen') }}">Naar de veilingen</a>
    <a class="btn btn-ghost" href="{{ url('/') }}">Naar de home</a>
</x-layouts.error>
