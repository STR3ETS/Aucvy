<x-layouts.error code="403" title="Hier mag je niet bij"
    message="Deze pagina hoort bij een ander account of bij beheer. Ben je ingelogd met het juiste account?">
    <a class="btn btn-primary" href="{{ url('/dashboard') }}">Naar je dashboard</a>
    <a class="btn btn-ghost" href="{{ url('/inloggen') }}">Anders inloggen</a>
</x-layouts.error>
