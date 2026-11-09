{{--
    De voettekst van de site (ontwerp van november): het merk met één zin,
    vier linkkolommen en onderaan de juridische regel. Links zonder eigen
    pagina wijzen naar de dichtstbijzijnde bestaande route of sectie.
--}}
@php
    $columns = [
        'Ontdek' => [
            ['Live veilingen', route('lots.index')],
            ['Direct kopen', route('lots.buy-now')],
            ['Categorieën', route('categories.index')],
            ['Hoe bieden werkt', route('how-it-works').'#kopen'],
        ],
        'Verkopen' => [
            ['Zakelijk verkopen', route('business')],
            ['Kavel plaatsen', route('sell')],
            ['Verkooproute', route('selling.guide')],
            ['Prijzen', route('pricing')],
            ['Verificatie', route('verification.overview')],
        ],
        'Hulp' => [
            ['Zekerheid en geschillen', route('assurance')],
            ['Veelgestelde vragen', route('faq')],
            ['Blog', route('blog.index')],
            ['Contact', route('contact')],
        ],
        'Account' => auth()->check()
            ? [
                ['Dashboard', auth()->user()->is_staff ? url('/admin') : route('dashboard')],
                ['Instellingen', route('account.settings')],
            ]
            : [
                ['Inloggen', route('login')],
                ['Registreren', route('register')],
            ],
    ];
@endphp

<footer class="footer-c">
    <div class="wrap">
        <div class="footer-c-cols">
            <div class="footer-c-brand-col">
                <a class="brand" href="{{ route('home') }}" aria-label="Aucvy - home"><svg class="mark" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8.8 40.8 L24 10 L39.2 40.8" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="35.6" r="6.9" fill="currentColor"/></svg>Aucvy</a>
                <p>Het AI-commerceplatform voor zakelijke goederen. Upload een foto en Ivy en haar team regelen de rest: van advertentie en waarde-indicatie tot biedingen en uitbetaling.</p>
            </div>

            @foreach ($columns as $heading => $links)
                <div>
                    <h4>{{ $heading }}</h4>
                    <ul>
                        @foreach ($links as [$text, $url])
                            <li><a href="{{ $url }}">{{ $text }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="footer-c-bottom">
            <span>© {{ now()->year }} Aucvy B.V.</span>
            <nav aria-label="Juridisch">
                <a href="{{ route('legal.terms') }}">Voorwaarden</a>
                <a href="{{ route('legal.privacy') }}">Privacy</a>
                <a href="{{ route('legal.cookies') }}">Cookies</a>
                <span class="by">Gebouwd door <a href="https://eazyonline.nl" target="_blank" rel="noopener noreferrer">Eazyonline</a></span>
            </nav>
        </div>
    </div>
</footer>
