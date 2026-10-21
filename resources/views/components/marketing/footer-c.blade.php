{{--
    De uitgebreide footer van variant C (hybride stijlgids): het merk met de
    vier beloftes, vier linkkolommen, rechts het donkere paneel met de
    hamerfoto uit ontwerp B, en onderaan de juridische regel. Links zonder
    eigen pagina wijzen naar de dichtstbijzijnde bestaande route of sectie.
--}}
@php
    $columns = [
        'Ontdek' => [
            ['Live veilingen', route('lots.index')],
            ['Nieuwe kavels', route('lots.index', ['toon' => 'nieuw'])],
            ['Laatste kans', route('lots.index', ['toon' => 'laatste-kans'])],
            ['Onder marktwaarde', route('lots.index', ['toon' => 'onder-marktwaarde'])],
            ['Categorieën', route('categories.index')],
            ['Hoe bieden werkt', route('how-it-works').'#kopen'],
        ],
        'Verkopen' => [
            ['Kavel plaatsen', route('sell')],
            ['Verkooproute', route('selling.guide')],
            ['Verkoopvormen', route('how-it-works').'#vormen'],
            ['Tarieven', route('faq')],
            ['Zakelijk verkopen', route('business')],
            ['Verificatie', route('verification.overview')],
        ],
        'Ivy' => [
            ['Hoe Ivy werkt', route('how-it-works').'#reis'],
            ['Zoeken met Ivy', route('lots.index')],
            ['Waarde-indicatie', route('faq')],
            ['Het team', route('business').'#team'],
            ['Zekerheid en geschillen', route('assurance')],
        ],
        'Aucvy' => [
            ['Zekerheid', route('assurance')],
            ['Veelgestelde vragen', route('faq')],
            ['Over ons', route('home')],
            ['Contact', route('home')],
            ['Inloggen', route('login')],
            ['Registreren', route('register')],
        ],
    ];
@endphp

<footer class="footer-c">
    <div class="wrap">
        <div class="footer-c-main">
            <div class="footer-c-cols">
                <div class="footer-c-brand-col">
                    <div class="brand"><svg class="mark" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8.8 40.8 L24 10 L39.2 40.8" stroke="currentColor" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/><circle cx="24" cy="35.6" r="6.9" fill="currentColor"/></svg> Aucvy</div>
                    <p>Het AI-commerceplatform voor zakelijke goederen. Upload een foto en Ivy en haar team regelen de rest: van advertentie en waarde-indicatie tot biedingen en uitbetaling.</p>
                    <ul class="footer-c-trust">
                        <li>Gratis plaatsen</li>
                        <li>Geen opgeld voor kopers</li>
                        <li>Alleen betalen bij resultaat</li>
                        <li>Geverifieerde verkopers</li>
                    </ul>
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

            <aside class="footer-c-panel" aria-labelledby="footer-c-panel-title">
                <div class="photo" aria-hidden="true" style="background-image:url('{{ asset('images/v4/footer-gavel.jpg') }}')"></div>
                <div class="shade" aria-hidden="true"></div>
                <h2 id="footer-c-panel-title">Slim bieden.<br><span class="g">Zeker van je zaak.</span></h2>
                <p>Waarde-indicaties, biedadvies en live biedingen van Ivy en haar team geven je het overzicht dat je zoekt.</p>
                <a class="btn btn-primary" href="{{ route('lots.index') }}">Ontdek de veilingen
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 8h10M9 4l4 4-4 4"/></svg></a>
            </aside>
        </div>

        <div class="footer-c-bottom">
            <span>© {{ now()->year }} Aucvy B.V.</span>
            <nav aria-label="Juridisch">
                <a href="{{ route('assurance') }}">Voorwaarden</a>
                <a href="{{ route('assurance') }}">Privacy</a>
                <a href="{{ route('assurance') }}">Cookies</a>
            </nav>
            <span class="by">Gebouwd door <a href="https://eazyonline.nl" target="_blank" rel="noopener noreferrer">Eazyonline</a></span>
        </div>
    </div>
</footer>
