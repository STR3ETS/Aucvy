<?php

declare(strict_types=1);

return [

    'errors' => [
        'not_completed' => 'Alleen een afgeronde bestelling geeft recht op een review.',
        'already_reviewed' => 'Je hebt deze bestelling al beoordeeld.',
        'already_replied' => 'Je hebt al op deze review gereageerd.',
    ],

    'published' => 'Dank je. Je beoordeling staat op het profiel van de verkoper.',
    'held' => 'Dank je. Je beoordeling wordt eerst door een medewerker bekeken (:reason) en staat daarna online als hij aan de regels voldoet.',
    'reported' => 'Dank je voor je melding. Een medewerker bekijkt de review; tot die tijd blijft hij zichtbaar.',

    // Dezelfde redenen voor de automatische controle, de meldknop en het besluit van beheer.
    'reasons' => [
        'offensive' => 'scheldwoorden of beledigingen',
        'discrimination' => 'discriminatie',
        'political' => 'politieke uitingen',
        'personal_data' => 'persoonsgegevens zoals een telefoonnummer of e-mailadres',
        'not_a_transaction' => 'gaat niet over deze aankoop',
        'other' => 'iets anders',
    ],

];
