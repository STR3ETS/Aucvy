<?php

declare(strict_types=1);

/*
 | De transactionele berichten rond orders en geld (hfst. 9: "elke overgang
 | stuurt beide partijen een bericht"). Toon als overal: zeggen wat er is en
 | wat er moet gebeuren - geen juichtaal, geen beloften.
 */

return [

    'created' => [
        'buyer' => [
            'subject' => 'Gefeliciteerd - ":lot" is van jou',
            'line' => 'Betaal vóór :deadline, anders vervalt de koop automatisch. Het bedrag en de kostenopbouw staan bij je bestelling.',
            'action' => 'Naar je bestelling',
        ],
        'seller' => [
            'subject' => '":lot" is verkocht',
            'line' => 'De koper heeft tot :deadline om te betalen. Zodra de betaling binnen is, hoor je het en kun je de levering regelen.',
            'action' => 'Bekijk de verkoop',
        ],
    ],

    'paid' => [
        'buyer' => [
            'subject' => 'Je betaling voor ":lot" is binnen',
            'line' => 'De verkoper maakt de kavel nu klaar. Je hoort het zodra hij onderweg is of klaarstaat om op te halen.',
            'action' => 'Naar je bestelling',
        ],
        'seller' => [
            'subject' => '":lot" is betaald - regel de levering',
            'line' => 'Zet de kavel klaar om op te halen of meld hem verzonden. Na zeven dagen zonder actie geldt hij automatisch als geleverd.',
            'action' => 'Regel de levering',
        ],
    ],

    'ready_for_pickup' => [
        'buyer' => [
            'subject' => '":lot" staat klaar om op te halen',
            'line' => 'Bevestig de levering zodra je alles hebt opgehaald en gecontroleerd - daarna gaat de meldtermijn van :window uur lopen.',
            'action' => 'Naar je bestelling',
        ],
    ],

    'shipped' => [
        'buyer' => [
            'subject' => '":lot" is onderweg',
            'line' => 'Bevestig de levering zodra alles binnen en gecontroleerd is - daarna gaat de meldtermijn van :window uur lopen.',
            'action' => 'Naar je bestelling',
        ],
    ],

    'delivered' => [
        'buyer' => [
            'subject' => 'Levering van ":lot" bevestigd',
            'line' => 'Klopt er toch iets niet? Meld het vóór :deadline - daarna wordt de bestelling automatisch afgerond.',
            'action' => 'Naar je bestelling',
        ],
        'seller' => [
            'subject' => '":lot" is geleverd',
            'line' => 'De meldtermijn van de koper loopt tot :deadline. Komt er geen melding, dan wordt je uitbetaling daarna vrijgegeven.',
            'action' => 'Bekijk de verkoop',
        ],
    ],

    'completed' => [
        'buyer' => [
            'subject' => '":lot" is afgerond - hoe was het?',
            'line' => 'Je bestelling is afgerond. Een eerlijke beoordeling - ook als het niet vlekkeloos liep - helpt de volgende koper.',
            'action' => 'Laat een beoordeling achter',
        ],
        'seller' => [
            'subject' => '":lot" is afgerond',
            'line' => 'De meldtermijn is zonder melding verstreken. Je uitbetaling wordt na de houdtermijn vrijgegeven; je krijgt bericht zodra dat gebeurd is.',
            'action' => 'Bekijk de verkoop',
        ],
    ],

    'disputed' => [
        'buyer' => [
            'subject' => 'Je melding over ":lot" is binnen',
            'line' => 'De verkoper mag eerst reageren; daarna beoordeelt een medewerker de melding - geen algoritme. De uitbetaling staat intussen stil.',
            'action' => 'Naar je bestelling',
        ],
        'seller' => [
            'subject' => 'Melding over ":lot" - reageer vóór :deadline',
            'line' => 'De koper heeft een probleem gemeld. Reageer met jouw kant van het verhaal; daarna beoordeelt een medewerker. Je uitbetaling staat stil tot het is afgehandeld.',
            'action' => 'Reageer op de melding',
        ],
    ],

    'cancelled' => [
        'buyer' => [
            'subject' => 'Bestelling ":lot" is geannuleerd',
            'line' => 'Deze koop gaat niet door. Heb je al betaald, dan wordt het bedrag teruggestort.',
            'action' => 'Naar je bestelling',
        ],
        'seller' => [
            'subject' => 'Verkoop ":lot" is geannuleerd',
            'line' => 'Deze verkoop gaat niet door; de kavel staat weer op jouw naam en kan opnieuw aangeboden worden.',
            'action' => 'Bekijk de verkoop',
        ],
    ],

    'dispute_resolved' => [
        'subject' => 'Uitkomst van het geschil over ":lot"',
        'intro' => 'Een medewerker heeft het geschil beoordeeld. De uitkomst: :resolution.',
        'notes' => 'Motivering: :notes',
        'action' => 'Naar de bestelling',
    ],

    'payout_released' => [
        'subject' => 'Je uitbetaling van :amount is vrijgegeven',
        'line' => 'De houdtermijn voor ":lot" is verstreken zonder melding. Het bedrag wordt overgemaakt.',
        'action' => 'Bekijk je verkopen',
    ],

    /*
     | Bezichtigingen (hfst. 7): het adres gaat pas naar de koper nadat de
     | verkoper bevestigd heeft - en uitsluitend per mail.
     */
    'viewing_requested' => [
        'seller' => [
            'subject' => 'Bezichtigingsaanvraag voor ":lot"',
            'line' => ':name wil komen kijken op :moment. Bevestig je het moment, dan sturen wij het adres door.',
            'note' => 'Bericht erbij: ":note"',
            'action' => 'Moment bevestigen',
            'decline' => 'Komt het niet uit? [Wijs het moment af](:url) - de aanvrager kan dan een nieuw moment voorstellen.',
        ],
    ],

    'viewing_confirmed' => [
        'buyer' => [
            'subject' => 'Bezichtiging bevestigd voor ":lot"',
            'line' => 'Je bent welkom op :moment.',
            'address' => 'Het adres: :address.',
            'action' => 'Naar de kavel',
        ],
    ],

    'viewing_cancelled' => [
        'buyer' => [
            'subject' => 'Bezichtiging voor ":lot" komt niet uit',
            'line' => 'De verkoper kan niet op :moment. Stel gerust een nieuw moment voor op de kavelpagina.',
            'action' => 'Nieuw moment voorstellen',
        ],
    ],

];
