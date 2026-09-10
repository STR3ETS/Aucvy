<?php

declare(strict_types=1);

return [

    'filters' => [
        'heading' => 'Filters',
        'category' => 'de categorie',
        'sale_type' => 'de verkoopvorm',
        'condition' => 'de conditie',
        'province' => 'de provincie',
        'price' => 'het prijsbereik',
        'quick' => 'de snelfilter',
        'viewing' => 'de eis dat bezichtiging mogelijk is',
        'transport' => 'de eis dat transport beschikbaar is',
        'clear' => 'Filters wissen',
        'show' => 'Filters',
        'active' => '{1} :count filter actief|[2,*] :count filters actief',
    ],

    'quick' => [
        'alles' => 'Alles',
        'live' => 'Live',
        'nieuw' => 'Nieuw',
        'laatste-kans' => 'Laatste kans',
        'onder-marktwaarde' => 'Onder marktwaarde',
    ],

    'sort' => [
        'label' => 'Sorteren',
        'relevantie' => 'Relevantie',
        'sluit-eerst' => 'Sluit eerst',
        'nieuwste' => 'Nieuwste',
        'prijs-op' => 'Prijs oplopend',
        'prijs-af' => 'Prijs aflopend',
        'meeste-biedingen' => 'Meeste bieders',
        'onder-waarde' => 'Grootste verschil met waarde-indicatie',
    ],

    'results' => [
        'count' => '{0} Geen kavels|{1} 1 kavel|[2,*] :count kavels',
        'load_more' => 'Meer laden',
    ],

    'empty' => [
        'title' => 'Geen kavels gevonden voor deze combinatie',
        // De lege staat is nooit doodlopend: één concrete verbreding, plus de
        // mogelijkheid een alert te zetten (hfst. 13.2).
        'suggestion' => 'Zonder :filter zijn het er :count.',
        'show_those' => 'Toon die :count',
        'set_alert' => 'Zet een alert',
        'nothing' => 'Er staan op dit moment geen kavels open. Zet een alert, dan hoor je het zodra er iets bijkomt.',
    ],

    'card' => [
        'below_value' => ':percent% onder waarde-indicatie',
        'bidders' => '{0} nog geen bieders|{1} 1 bieder|[2,*] :count bieders',
        'dutch_drops' => 'daalt elke :seconds s',
        'no_bids_yet' => 'nog geen biedingen',
    ],

];
