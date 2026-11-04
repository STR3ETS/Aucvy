<?php

declare(strict_types=1);

/*
 | Het control center (briefing nieuwe functies).
 */
return [

    'forbidden' => 'Dit onderdeel hoort niet bij jouw rol.',

    'roles' => [
        'owner' => 'Eigenaar',
        'finance' => 'Financiën',
        'support' => 'Klantenservice',
        'moderator' => 'Moderator',
        'marketing' => 'Marketing',
    ],

    'tickets' => [
        'types' => ['question' => 'Vraag', 'complaint' => 'Klacht'],
        'priorities' => ['low' => 'Laag', 'normal' => 'Normaal', 'high' => 'Hoog'],
        'sources' => ['form' => 'Contactformulier', 'mail' => 'Mail', 'chat' => 'Chat', 'conversation' => 'Gesprek'],
    ],

    'costs' => [
        'payment' => 'Betaalkosten',
        'ai_hosting' => 'AI en hosting',
        'mail_live' => 'Mail en live',
        'marketing' => 'Marketing',
        'team' => 'Team',
        'other' => 'Overig',
    ],

    'ledger' => [
        'fee' => 'Succesfee',
        'admin_fee' => 'Administratiekosten',
        'subscription' => 'Abonnementen',
        'promotion' => 'Promotie',
        'refund' => 'Terugbetaald',
        'payment_cost' => 'Betaalkosten',
    ],

    'definitions' => [
        'volume' => 'Som van betaalde bestellingen in de periode, min terugbetalingen, excl. btw. Dit geld is niet van Aucvy.',
        'transactions' => 'Bestellingen met status betaald in de periode.',
        'revenue' => 'Succesfee + abonnementen + promotie, min terugbetaald, excl. btw.',
        'costs' => 'Betaalkosten, AI en hosting, mail en live, marketing, team, overig.',
        'profit' => 'Platformomzet min kosten, vóór belasting. Stuurcijfer, geen jaarcijfer.',
        'lots_live' => 'Kavels die nu zichtbaar zijn en biedingen aannemen.',
        'users' => 'Accounts met geverifieerd e-mailadres, zonder test- en verwijderde accounts.',
        'tickets' => 'Tickets die niet zijn opgelost of gesloten.',
        'complaints' => 'Tickets van het type klacht plus open geschillen.',
        'first_response' => 'Tijd tot het eerste antwoord van een mens.',
    ],

    'reports' => [
        'revenue' => 'Omzet en winst per maand',
        'transactions' => 'Transacties',
        'lots' => 'Kavels',
        'customers' => 'Klanten',
        'tickets' => 'Tickets en klachten',
        'payouts' => 'Uitbetalingen',
    ],

];
