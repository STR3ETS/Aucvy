<?php

declare(strict_types=1);

/*
 | Auction Studio: veilingen als verzameling kavels.
 */

return [

    'created' => 'De veiling staat klaar. Zet er kavels in en publiceer hem als het schema klopt.',
    'updated' => 'De veiling is opgeslagen.',
    'rescheduled' => 'De veiling is opgeslagen; :count kavels kregen de nieuwe sluittijd.',
    'kept' => ' Bij :count kavels is al geboden, die houden hun sluittijd.',
    'attached' => '{1} Eén kavel toegevoegd.|[2,*] :count kavels toegevoegd.',
    'detached' => 'De kavel staat niet meer in deze veiling.',
    'published' => 'De veiling is gepubliceerd. Concepten erin gaan live zodra je ze publiceert.',
    'drafts_published' => '{0} Geen concepten gepubliceerd.|{1} Eén kavel is nu live.|[2,*] :count kavels zijn nu live.',
    'drafts_failed' => ' :count kavels bleven concept; de reden staat erbij.',
    'cancelled' => 'De veiling is geannuleerd. Concepten staan weer los.',

    'errors' => [
        'not_attachable' => 'Alleen een eigen concept kan in een veiling; een kavel die al live is houdt zijn eigen looptijd.',
        'in_other_sale' => 'Deze kavel zit al in een andere veiling.',
        'cancelled' => 'Deze veiling is geannuleerd.',
        'ends_too_soon' => 'De eerste sluiting moet minstens :minutes minuten in de toekomst liggen.',
        'no_lots' => 'Zet eerst minstens één kavel in de veiling.',
        'has_bids' => 'Er is al geboden op een kavel in deze veiling; annuleren kan niet meer.',
        'not_published' => 'Publiceer eerst de veiling; dan krijgen de kavels hun sluittijd.',
        'closed' => 'Deze veiling is gesloten; maak een nieuwe veiling voor wat overbleef.',
        'slot_passed' => 'Het sluitmoment van deze kavel in de veiling is al voorbij. Haal hem uit de veiling of zet hem in een nieuwe.',
    ],

];
