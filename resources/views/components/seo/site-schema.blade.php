{{--
    Wie Aucvy is en hoe je zoekt, voor zoekmachines: de organisatie en de
    website met een zoekvak (sitelinks searchbox). Alleen op de home.
--}}
<x-seo.schema :data="[
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => url('/').'#organisatie',
            'name' => config('app.name'),
            'url' => url('/'),
            'logo' => asset('icon-512.png'),
            'description' => 'Het zakelijke veilingplatform voor bedrijfsgoederen, met Ivy als AI-assistent voor verkopers en kopers.',
        ],
        [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'name' => config('app.name'),
            'url' => url('/'),
            'inLanguage' => 'nl-NL',
            'publisher' => ['@id' => url('/').'#organisatie'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('lots.index').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ],
    ],
]" />
