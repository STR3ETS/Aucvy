<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Wat er op Aucvy aangeboden mag worden
|--------------------------------------------------------------------------
|
| Vraag 17 aan Raph: "Ga ik aanleveren, maar je kunt ook kijken wat de
| andere platformen verbieden, houd dat gelijk." Deze lijst is daarom een
| CONCEPT, gelijkgetrokken met wat gangbare veiling- en verkoopplatformen
| in Nederland en Europa uitsluiten (wapens, drugs, medicijnen, levende
| dieren, beschermde soorten, namaak, gestolen of herkomstloze waar,
| gevaarlijke stoffen, teruggeroepen producten). Raph vult hem aan.
|
| Elke groep heeft een naam (voor de publieke pagina en de reden aan de
| verkoper) en trefwoorden. Een treffer in titel of omschrijving stuurt de
| kavel bij de controle vóór publicatie naar een medewerker; hij wordt nooit
| automatisch afgewezen op een woord, want "jachtgeweer-kast" is geen
| geweer. Trefwoorden tellen als heel woord of begin van een samenstelling.
|
*/

return [

    'unit_options' => [
        'stuk' => 'per stuk',
        'set' => 'per set',
        'partij' => 'per partij',
        'pallet' => 'per pallet',
        'doos' => 'per doos',
        'm2' => 'per m²',
        'meter' => 'per meter',
        'kg' => 'per kg',
        'liter' => 'per liter',
    ],

    'warranty_options' => [
        'none' => 'Geen garantie',
        'manufacturer' => 'Fabrieksgarantie',
        'seller' => 'Garantie van de verkoper',
    ],

    'forbidden_goods' => [
        'wapens' => [
            'label' => 'Wapens, munitie en onderdelen',
            'keywords' => ['vuurwapen', 'pistool', 'revolver', 'geweer', 'munitie', 'patronen', 'kogels', 'luchtbuks', 'taser', 'stroomstootwapen', 'boksbeugel', 'vlindermes', 'valmes', 'werpster', 'pepperspray', 'traangas'],
        ],
        'explosieven' => [
            'label' => 'Explosieven en vuurwerk',
            'keywords' => ['explosief', 'explosieven', 'vuurwerk', 'springstof', 'ontsteker', 'detonator', 'handgranaat'],
        ],
        'drugs' => [
            'label' => 'Drugs en drugsgerelateerde artikelen',
            'keywords' => ['cannabis', 'wiet', 'hasj', 'cocaine', 'xtc', 'mdma', 'lachgas', 'growbox', 'kweekset'],
        ],
        'medicijnen' => [
            'label' => 'Geneesmiddelen en medische hulpmiddelen op recept',
            'keywords' => ['receptplichtig', 'geneesmiddel', 'medicijnen', 'antibiotica', 'opiaten', 'insuline', 'injectienaalden'],
        ],
        'dieren' => [
            'label' => 'Levende dieren en beschermde dier- en plantensoorten',
            'keywords' => ['levend dier', 'levende dieren', 'pup', 'kitten', 'ivoor', 'neushoornhoorn', 'schildpaddenschild', 'cites'],
        ],
        'namaak' => [
            'label' => 'Namaak, replica\'s en inbreuk op merken',
            'keywords' => ['namaak', 'replica', 'nep', 'kopie van', 'fake'],
        ],
        'herkomst' => [
            'label' => 'Gestolen goederen of goederen zonder aantoonbare herkomst',
            'keywords' => ['gestolen', 'zonder papieren', 'geen papieren', 'zonder kenteken', 'geheeld'],
        ],
        'gevaarlijk' => [
            'label' => 'Gevaarlijke stoffen en teruggeroepen producten',
            'keywords' => ['asbest', 'kwik', 'radioactief', 'gifstof', 'teruggeroepen', 'recall'],
        ],
        'persoonsgegevens' => [
            'label' => 'Persoonsgegevens, accounts en documenten van personen',
            'keywords' => ['paspoort', 'identiteitskaart', 'rijbewijs', 'klantenbestand', 'adressenbestand', 'database met klanten'],
        ],
        'tabak' => [
            'label' => 'Tabak, e-sigaretten en alcohol aan particulieren',
            'keywords' => ['sigaretten', 'shag', 'vape', 'e-sigaret', 'tabak'],
        ],
    ],

    /*
     | Een prijs die meer dan zoveel keer boven de bovenkant van de
     | waarde-indicatie ligt is vaak een tikfout (centen voor euro's). De
     | kavel gaat dan terug naar de verkoper met de vraag de prijs te
     | controleren; alleen bij een waarde-indicatie met redelijke zekerheid.
     */
    'price_outlier_factor' => 5,

];
