<?php

declare(strict_types=1);

return [

    'errors' => [
        'invalid_transition' => 'Van ":from" naar ":to" kan niet - deze stap bestaat niet in de orderroute.',
        'not_yours' => 'Deze bestelling is niet van jou.',
        'not_payable' => 'Deze bestelling staat niet (meer) open voor betaling.',
        'dispute_window_closed' => 'De meldtermijn van deze bestelling is verstreken.',
        'dispute_not_open' => 'Dit geschil staat niet (meer) open.',
    ],

    /*
     | De keuzes in het probleemformulier. De sleutel gaat de database in
     | (`disputes.reason`), de tekst is wat de koper kiest.
     */
    'dispute_reasons' => [
        'not_as_described' => 'Wijkt af van de omschrijving',
        'damaged' => 'Beschadigd ontvangen',
        'incomplete' => 'Niet compleet',
        'not_working' => 'Werkt niet',
        'not_received' => 'Niet ontvangen',
        'other' => 'Iets anders',
    ],

    /*
     | Wat elke status voor de kijker betekent - koper en verkoper zien
     | dezelfde route, met hun eigen vervolgstap eronder.
     */
    'timeline' => [
        'awaiting_payment' => 'Betaal binnen de termijn, anders vervalt de koop.',
        'paid' => 'Betaling ontvangen. De verkoper maakt de kavel klaar.',
        'ready_for_pickup' => 'De kavel staat klaar om opgehaald te worden.',
        'shipped' => 'Onderweg. Bevestig de levering zodra alles binnen en gecontroleerd is.',
        'delivered' => 'Geleverd. Tot het einde van de meldtermijn kun je een probleem melden.',
        'completed' => 'Afgerond. De uitbetaling aan de verkoper is vrijgegeven of staat ingepland.',
        'disputed' => 'Er loopt een geschil. De uitbetaling staat stil tot het is afgehandeld.',
        'cancelled' => 'Geannuleerd.',
    ],

];
