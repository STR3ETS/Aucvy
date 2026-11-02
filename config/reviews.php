<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Reviewmoderatie
|--------------------------------------------------------------------------
|
| Het Google-principe (besluit Raph, 2 november 2026): een review gaat door
| een automatische controle vóór publicatie. Wat op deze lijsten staat wordt
| niet gepubliceerd maar door een medewerker beoordeeld. Een woord telt als
| heel woord of, vanaf vijf letters, als begin van een samenstelling
| ("kankerverkoper"); partijnamen alleen als heel woord. Hoofdletters, accenten en
| vermommingen als "k4nker" of "k*nker" worden gelijkgetrokken.
|
| Bewust kort en grof: liever een review te veel ter beoordeling dan een
| scheldwoord op een verkopersprofiel. Aanvullen kan hier, zonder code.
|
*/

return [

    'moderation' => [

        // Scheldwoorden en grove beledigingen.
        'offensive' => [
            'kanker', 'tering', 'tyfus', 'pleuris', 'klootzak', 'kutwijf', 'kut',
            'lul', 'eikel', 'hoer', 'teef', 'mongool', 'debiel', 'idioot', 'oplichter',
            'fuck', 'shit', 'bitch', 'asshole', 'motherfucker',
        ],

        // Discriminatie op afkomst, geloof, geaardheid of beperking.
        'discrimination' => [
            'neger', 'nikker', 'makaak', 'kakkerlak', 'zandneger', 'geitenneuker',
            'jodenstreek', 'kankerjood', 'flikker', 'spast', 'spastisch',
        ],

        // Politieke kleuring: partijnamen en politici horen niet in een
        // beoordeling van een transactie.
        'political' => [
            'pvv', 'vvd', 'cda', 'd66', 'groenlinks', 'pvda', 'sp', 'bbb', 'fvd',
            'forum voor democratie', 'ja21', 'nsc', 'partij voor de dieren',
            'wilders', 'baudet', 'timmermans', 'yesilgoz', 'omtzigt',
        ],
    ],

    /*
     | Vanaf hoeveel openstaande meldingen een gepubliceerde review naar boven
     | in de beheerlijst komt. Een gemelde review blijft zichtbaar tot een
     | medewerker beslist; melden alleen haalt niets weg.
     */
    'reports_to_flag' => 1,

];
