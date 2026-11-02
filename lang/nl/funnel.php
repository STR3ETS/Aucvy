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

    /*
     | Herkomst per veld (briefing sectie 5): wie heeft dit ingevuld en hoe
     | zeker is dat.
     */
    'provenance' => [
        'seller' => 'Door jou ingevuld',
        'photo' => 'Ivy, uit je foto\'s (:percent% zeker)',
        'document' => 'Ivy, uit je documenten (:percent% zeker)',
        'ivy' => 'Geschreven door Ivy (:percent% zeker)',
        'benchmark' => 'Indicatie op basis van :count vergelijkbare aanbiedingen',
    ],

    'fields' => [
        'title' => 'titel',
        'description' => 'omschrijving',
        'category_id' => 'categorie',
        'condition' => 'conditie',
        'brand' => 'merk',
        'model' => 'model',
        'year' => 'bouwjaar',
        'value' => 'waarde-indicatie',
    ],

    /*
     | De preflight van stap 8: label en, als het niet in orde is, wat te doen.
     */
    'preflight' => [
        'title' => 'Titel',
        'title_hint' => 'Geef de kavel een titel.',
        'category' => 'Categorie',
        'category_hint' => 'Kies een categorie, dan vinden kopers hem.',
        'forbidden' => 'Toegestane categorie',
        'forbidden_hint' => 'Deze categorie mag niet op Aucvy worden aangeboden.',
        'photos' => 'Minstens één foto',
        'photos_hint' => 'Zonder foto gaat een kavel niet live. Eén foto van je telefoon is genoeg.',
        'condition' => 'Conditie',
        'condition_hint' => 'Aanbevolen: kopers vragen er als eerste naar.',
        'description' => 'Omschrijving van minstens veertig tekens',
        'description_hint' => 'Aanbevolen: eerlijk over gebreken verkoopt aantoonbaar beter.',
        'pricing' => 'Verkoopvorm en prijzen',
        'pricing_hint' => 'Kies hoe je verkoopt en vul de bijbehorende bedragen in.',
        'price_order' => 'Prijzen in de juiste volgorde',
        'price_order_hint' => 'De reserveprijs niet onder de startprijs, koop-direct boven start en reserve, en de bodem van een afslag tussen reserve en startprijs.',
        'ownership' => 'Eigendom',
        'ownership_hint' => 'Verklaar dat je eigenaar of gemachtigd bent, of voeg een eigendomsbewijs of factuur toe.',
        'unit' => 'Hoeveelheid en eenheid',
        'unit_hint' => 'Geef aan hoeveel je verkoopt en waarin dat geteld wordt: per stuk, per set, per pallet.',
        'warranty' => 'Garantie',
        'warranty_hint' => 'Geef aan of er garantie op zit: geen, fabrieksgarantie of garantie van jou als verkoper.',
        'documents_authentic' => 'Documenten verklaard',
        'documents_authentic_hint' => 'Vink aan dat de documenten die je toevoegde echt en volledig zijn.',
        'confirmed' => 'Ivy-velden bevestigd',
        'confirmed_hint' => 'Bevestig of corrigeer eerst: :fields.',
        'account' => 'Ingelogd',
        'account_hint' => 'Om te publiceren log je eerst in; je concept blijft bewaard.',
        'permission' => 'Verificatie',
        'permission_hint' => 'Rond je verificatie af voordat je kavel live kan.',
    ],

    'errors' => [
        'reserve_below_start' => 'De reserveprijs kan niet onder de startprijs liggen.',
        'buy_now_below_prices' => 'De koop-directprijs moet boven de start- en reserveprijs liggen; anders koopt iemand voor minder dan je zelf wilt.',
        'dutch_floor_below_reserve' => 'De bodemprijs van de afslag kan niet onder de reserveprijs liggen.',
        'not_yours' => 'Dit concept is van iemand anders. Alleen de eigenaar of een collega met de juiste rol kan het publiceren.',
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
