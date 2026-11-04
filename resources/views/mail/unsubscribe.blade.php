<x-layouts.public title="Afmelden" heading="Afmelden voor mail" :intro="$stream === 'campaign' ? 'Nieuwsbrief en campagnes van Aucvy.' : 'Herinneringen en tips van Ivy.'" align="center" noindex>
    <div class="mx-auto max-w-md d-card text-center">
        @if ($done || session('status'))
            <p class="text-sm font-bold">Je bent afgemeld.</p>
            <p class="mt-2 text-sm text-aj-muted">Mails die nodig zijn voor je account of een aankoop (betaalverzoek, uitbetaling, geschil) blijven komen. Lopende reeksen zijn gestopt.</p>
        @else
            <p class="text-sm">Wil je geen {{ $stream === 'campaign' ? 'nieuwsbrief en campagnes' : 'herinneringen en tips' }} meer ontvangen op <span class="font-semibold">{{ $user->email }}</span>?</p>
            <form method="POST" action="{{ $url }}" class="mt-4">
                @csrf
                <button type="submit" class="btn btn-dark" style="height:42px;font-size:14px">Ja, meld me af</button>
            </form>
            <p class="mt-3 text-xs text-aj-muted">Servicemails over je account en aankopen blijven komen; die kun je niet uitzetten.</p>
        @endif
        <a href="{{ route('account.settings') }}" class="mt-4 inline-block text-sm font-semibold underline">Naar je mailvoorkeuren</a>
    </div>
</x-layouts.public>
