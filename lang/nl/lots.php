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

    /*
     | Eigen kavels beheren (briefing sectie 4).
     */
    'manage' => [
        'saved' => 'Je wijzigingen zijn opgeslagen.',
        'translating' => 'Ivy vertaalt de advertentie; het Engels staat binnen een minuut op de kavelpagina.',
        'translate_off' => 'Vertalen staat nu uit, of de kavel is niet publiek.',
        'not_editable' => 'Deze kavel is niet meer te bewerken: hij is afgerond of nog een concept. Een concept bewerk je in de wizard.',
        'title_locked' => 'De titel staat vast zodra er geboden is; bieders hebben daarop geboden.',
        'cannot_pause' => 'Alleen een lopende of ingeplande veiling is te pauzeren.',
        'cannot_pause_bids' => 'Pauzeren kan niet meer: er is al geboden.',
        'not_paused' => 'Deze kavel is niet gepauzeerd.',
        'paused' => 'De kavel is gepauzeerd. Hij is even niet te zien; de resterende tijd loopt bij hervatten verder.',
        'resumed' => 'De kavel loopt weer. De eindtijd is opgeschoven met de tijd die hij stilstond.',
        'duplicated' => 'Gedupliceerd als concept #:id. Loop de stappen na en publiceer wanneer je klaar bent.',
        'relisted' => 'Opnieuw klaargezet als concept #:id. Controleer de prijs en publiceer wanneer je klaar bent.',
        'bulk_delete_drafts' => '{0} Geen concepten verwijderd.|{1} Eén concept verwijderd.|[2,*] :count concepten verwijderd.',
        'bulk_duplicate' => '{0} Niets gedupliceerd.|{1} Eén kavel gedupliceerd als concept.|[2,*] :count kavels gedupliceerd als concept.',
    ],

];
