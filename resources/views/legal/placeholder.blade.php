<x-layouts.public :title="$title" eyebrow="Juridisch" :heading="$title" :noindex="true"
    intro="Deze tekst wordt op dit moment opgesteld en staat hier voor de lancering.">
    <div class="mx-auto max-w-3xl space-y-4 text-sm leading-relaxed">
        <p>{{ $lead }}</p>
        <p>Vragen in de tussentijd? Neem <a href="{{ route('contact') }}" class="font-semibold underline">contact</a> met ons op.</p>
        <p class="text-aj-muted">Zie ook de <a href="{{ route('legal.cookies') }}" class="font-semibold underline">cookieverklaring</a>.</p>
    </div>
</x-layouts.public>
