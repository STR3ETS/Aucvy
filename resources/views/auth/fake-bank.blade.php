<x-layouts.auth title="Nepbank" heading="Nepbank (lokaal)"
    intro="Zo ziet de stap bij de bank eruit tot de iDIN-partij gekozen is. Er wordt niets gecontroleerd.">
    <div class="space-y-3">
        <a href="{{ $confirmUrl }}" class="btn btn-primary w-full justify-center">Identiteit bevestigen</a>
        <a href="{{ $cancelUrl }}" class="btn btn-ghost w-full justify-center">Annuleren</a>
    </div>
</x-layouts.auth>
