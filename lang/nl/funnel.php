<?php

declare(strict_types=1);

return [

    /*
     | De vier drempelteksten bij de compleetheidsscore (briefing hfst. 13.4).
     */
    'completeness' => [
        'start' => 'Een goed begin - voeg foto\'s en een omschrijving toe.',
        'good' => 'Dit begint ergens op te lijken. Documenten maken het af.',
        'strong' => 'Sterk - nog één element en je kavel hoort bij de best gedocumenteerde.',
        'complete' => 'Compleet. Kavels als deze verkopen aantoonbaar beter.',
    ],

    'missing' => [
        'title' => 'Een titel van minstens tien tekens',
        'description' => 'Een omschrijving van minstens veertig tekens',
        'category' => 'Een categorie',
        'condition' => 'De conditie',
        'brand' => 'Merk of type',
        'photos' => 'Minstens één foto',
        'video' => 'Een video of 360°-opname',
        'documents' => 'Minstens één document',
        'transport' => 'Transportinformatie of "zelf ophalen"',
        'viewing' => 'Een kijkmoment',
    ],

    'errors' => [
        'login_required' => 'Log in om je kavel te publiceren. Je concept blijft bewaard.',
        'verification_required' => 'Rond eerst je verificatie af voordat je kavel live kan.',
        'title_required' => 'Een kavel heeft een titel nodig.',
        'category_required' => 'Kies eerst een categorie.',
        'sale_type_required' => 'Kies hoe je wilt verkopen: veiling, afslag of koop direct.',
        'start_price_required' => 'Een veiling heeft een startprijs nodig.',
        'buy_now_price_required' => 'Koop direct heeft een vaste prijs nodig.',
        'dutch_settings_required' => 'Een afslag heeft een startprijs, bodemprijs, stap en interval nodig.',
        'dutch_floor_above_start' => 'De bodemprijs kan niet boven de startprijs liggen.',
        'not_a_draft' => 'Deze kavel is al gepubliceerd.',
    ],

];
