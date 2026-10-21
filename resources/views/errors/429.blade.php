<x-layouts.error code="429" title="Even rustig aan"
    message="Je deed te veel aanvragen achter elkaar. Wacht een minuut en probeer het dan opnieuw.">
    <a class="btn btn-primary" href="{{ url()->previous() }}">Terug</a>
</x-layouts.error>
