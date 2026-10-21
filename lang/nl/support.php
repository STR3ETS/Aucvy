<?php

declare(strict_types=1);

/*
 | Het contactformulier en de supporttickets.
 */

return [

    'sent' => 'Je bericht is binnen (ticket #:id). Je krijgt een bevestiging per mail; we reageren meestal binnen één werkdag.',
    'closed' => 'Ticket #:id is gesloten.',
    'reopened' => 'Ticket #:id is heropend.',
    'already_closed' => 'Ticket #:id was al gesloten.',
    'already_open' => 'Ticket #:id staat al open.',

    'received' => [
        'subject' => 'We hebben je bericht ontvangen (#:id)',
        'greeting' => 'Hoi :name,',
        'line' => 'Je bericht over ":subject" is binnen. We reageren meestal binnen één werkdag, per mail.',
        'quote' => 'Je schreef: ":message"',
        'next' => 'Gaat het om een lopende bestelling met een probleem? Meld dat dan ook via de knop "Probleem melden" bij de bestelling: daar lopen de termijnen.',
    ],

    'forwarded' => [
        'subject' => 'Support #:id: :subject',
        'from' => 'Van :name (:email). Beantwoorden kan direct op deze mail.',
        'order' => 'Gaat over bestelling :order.',
        'account' => 'Account in de backoffice: :url',
        'action' => 'Open de supportlijst',
    ],

];
