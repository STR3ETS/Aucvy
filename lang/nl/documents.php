<?php

declare(strict_types=1);

/*
 | Documenten bij een kavel en het oogje (briefing nieuwe functies).
 */
return [

    'visibility' => [
        'public' => 'Openbaar',
        'after_sale' => 'Na gunning',
        'private' => 'Privé',
    ],

    'visibility_help' => [
        'public' => 'Iedereen op de kavelpagina.',
        'after_sale' => 'De winnaar, jij en medewerkers, zodra de veiling gesloten is.',
        'private' => 'Alleen jij, Lex en medewerkers. Kopers zien dat het bestaat en is gecontroleerd.',
    ],

    'locks' => [
        'after_sale' => [
            'title' => 'Te zien na gunning',
            'text' => 'De winnaar en de verkoper kunnen dit document openen zodra de veiling is gesloten.',
        ],
        'private' => [
            'title' => 'Privé document',
            'text' => 'Dit document blijft privé. Lex heeft het gecontroleerd, maar de inhoud is niet zichtbaar op de kavelpagina.',
        ],
        'login' => [
            'title' => 'Log in om te bekijken',
            'text' => 'Dit document is te bekijken met een account.',
        ],
    ],

    'checked_by_lex' => 'Gecontroleerd door Lex',
    'public_warning' => 'Let op: een openbaar document kan iedereen lezen. Scherm persoonsgegevens, prijzen en rekeningnummers eerst af.',

    'status' => [
        'visibility_saved' => ':kind staat nu op :level.',
    ],

];
