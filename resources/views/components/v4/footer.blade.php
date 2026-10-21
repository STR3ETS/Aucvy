{{--
    De footer uit preview v4: een donker afgerond vlak met vier linkkolommen
    en rechts het merkpaneel met de hamerfoto. Links zonder bestaande pagina
    wijzen naar de dichtstbijzijnde bestaande route.
--}}
@php
    $columns = [
        'Ontdek' => [
            ['Live veilingen', route('lots.index')],
            ['Categorieën', route('home').'#ontdek-categorieen'],
            ['Nieuwe kavels', route('lots.index', ['toon' => 'nieuw'])],
            ['Laatste kans', route('lots.index', ['toon' => 'laatste-kans'])],
            ['Onder marktwaarde', route('lots.index', ['toon' => 'onder-marktwaarde'])],
            ['Hoe het werkt', route('home').'#verkoopvormen'],
        ],
        'Verkopen' => [
            ['Plaats een kavel', route('sell')],
            ['Complete inventaris', route('home').'#zakelijk'],
            ['Promoten met Ivy', route('home').'#ivy-engine'],
            ['Tarieven', route('home').'#tarieven'],
            ['Verkooproute', route('selling.guide')],
        ],
        'Ivy' => [
            ['Hoe Ivy werkt', route('home').'#ivy-engine'],
            ['Ivy voor verkopers', route('home').'#verkopersstory'],
            ['Ivy voor kopers', route('home').'#kopersstory'],
            ['Zekerheid en geschillen', route('assurance')],
            ['Veelgestelde vragen', route('faq')],
        ],
        'Zakelijk' => [
            ['Curatoren', route('home').'#zakelijk'],
            ['Retail', route('home').'#zakelijk'],
            ['Lease en remarketing', route('home').'#zakelijk'],
            ['Overheden', route('home').'#zakelijk'],
            ['White-label veiling', route('home').'#zakelijk'],
        ],
    ];
@endphp

<footer>
    <div class="v4-foot">
        <svg aria-hidden="true" width="440" height="360" viewBox="0 0 440 360" fill="none" style="position:absolute;left:-40px;bottom:40px;opacity:.5;pointer-events:none">
            <path d="M20 40 C 150 130, 240 250, 430 300" stroke="rgba(150,120,255,.28)" stroke-width="1" stroke-dasharray="3 5" fill="none"/>
            <path d="M-10 300 C 120 260, 200 160, 300 20" stroke="rgba(150,120,255,.2)" stroke-width="1" stroke-dasharray="3 5" fill="none"/>
            <circle cx="128" cy="232" r="3.5" fill="#B08CFF"/>
            <circle cx="128" cy="232" r="9" fill="#B08CFF" opacity=".22"/>
        </svg>

        <div class="v4-footer-main">
            <div class="v4-footer-cols">
                <div class="v4-footer-grid">
                    <div>
                        <span class="v4-brand" style="color:#fff;font-size:23px"><x-v4.logo :size="34" />Aucvy</span>
                        <p>Het AI-commerceplatform. Upload en Ivy regelt de rest: van advertentie en promotie tot biedingen en afhandeling.</p>
                    </div>
                    @foreach ($columns as $heading => $links)
                        <div>
                            <h4>{{ $heading }}</h4>
                            @foreach ($links as [$text, $url])
                                <a href="{{ $url }}">{{ $text }}</a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="v4-footer-brand">
                <div class="photo" aria-hidden="true" style="background-image:url('{{ asset('images/v4/footer-gavel.jpg') }}')"></div>
                <div class="shade" aria-hidden="true"></div>
                <h2>Slim bieden.<br><span>Goed geïnformeerd.</span></h2>
                <p>Real-time data en Ivy's inzichten geven jou het voordeel dat je zoekt.</p>
                <a href="{{ route('lots.index') }}" class="cta">Ontdek Aucvy<i aria-hidden="true"><x-v4.icon name="arrow-right" :size="17" stroke="2.6" /></i></a>
            </div>
        </div>

        <div class="v4-footer-bottom">
            <div>
                <span>© {{ now()->year }} Aucvy B.V.</span>
                <span class="sep" aria-hidden="true"></span>
                <a href="{{ route('assurance') }}">Zekerheid</a>
                <a href="{{ route('faq') }}">Veelgestelde vragen</a>
                <a href="{{ route('selling.guide') }}">Verkooproute</a>
            </div>
            <div>
                <span class="by">Built by <a href="https://eazyonline.nl" target="_blank" rel="noopener noreferrer">Eazyonline</a></span>
            </div>
        </div>
    </div>
</footer>
