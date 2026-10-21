<?php

declare(strict_types=1);

return [

    'errors' => [
        'invalid_transition' => 'Van ":from" naar ":to" kan niet - deze stap bestaat niet in de orderroute.',
        'not_yours' => 'Deze bestelling is niet van jou.',
        'not_payable' => 'Deze bestelling staat niet (meer) open voor betaling.',
        'dispute_window_closed' => 'De meldtermijn van deze bestelling is verstreken.',
        'dispute_not_open' => 'Dit geschil staat niet (meer) open.',
        'correction_required' => 'Een prijscorrectie heeft een bedrag nodig.',
    ],

    'dispute_responded' => 'Je reactie is vastgelegd. Een medewerker beoordeelt het geschil en je hoort de uitkomst per mail.',

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
    /*
     | De tijdlijn op de bestelpagina: wat er gebeurd is.
     */
    'events' => [
        'created' => 'Hamerslag: de koop is gesloten',
        'created_body' => 'Hamerbedrag :amount. De betaaltermijn is gaan lopen.',
        'awaiting_payment' => 'Wacht op betaling',
        'paid' => 'Betaling ontvangen',
        'payment' => 'Betaling verwerkt',
        'payment_body' => ':amount via :method.',
        'ready_for_pickup' => 'Klaar om op te halen',
        'shipped' => 'Verzonden',
        'method_pickup' => 'De verkoper heeft de kavel klaargezet.',
        'method_shipped' => 'De verkoper heeft de kavel verzonden.',
        'delivered' => 'Levering bevestigd',
        'delivered_auto' => 'Automatisch, na zeven dagen zonder melding.',
        'completed' => 'Afgerond',
        'completed_body' => 'De meldtermijn is verstreken; de uitbetaling staat ingepland na :until.',
        'disputed' => 'Probleem gemeld',
        'disputed_body' => 'Reden: :reason. De uitbetaling staat stil.',
        'dispute_response' => 'Reactie van de verkoper',
        'dispute_resolved' => 'Geschil beoordeeld',
        'cancelled' => 'Geannuleerd',
        'payout' => 'Uitbetaling vrijgegeven',
        'payout_body' => ':amount gaat naar de verkoper.',
    ],

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
