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

    /*
     | AVG (hfst. 15): inzage-export en de tweede inlogstap voor accounts
     | met een gekoppelde uitbetaling.
     */
    'data_export' => [
        'subject' => 'Je gegevensexport staat klaar',
        'line' => 'Alles wat Aucvy over je bewaart, in een leesbaar bestand.',
        'action' => 'Download je gegevens',
        'expires' => 'De link werkt 24 uur en alleen als je ingelogd bent.',
    ],

    /*
     | De mails uit de briefing (sectie 8) die nog ontbraken: welkom,
     | overboden zonder push, verloren en de uitkomst van de verificatie.
     */
    'welcome' => [
        'subject' => 'Welkom bij Aucvy',
        'greeting' => 'Welkom, :name',
        'line' => 'Je account staat. Rondkijken, volgen en een kavel voorbereiden kan meteen.',
        'next' => 'Vóór je eerste bod of je eerste gepubliceerde kavel vragen we je verificatie af te ronden. Dat duurt een paar minuten.',
        'action' => 'Verificatie afronden',
        'browse' => 'Liever eerst kijken? De veilingen staan open zonder account.',
    ],

    'outbid' => [
        'subject' => 'Je bent overboden op ":lot"',
        'line' => ':lot staat nu op :price.',
        'manual' => 'De veiling loopt nog. Bied opnieuw of laat hem lopen: jij beslist.',
        'automatic' => 'Een ander had een hoger maximum ingesteld, waardoor zijn bod automatisch meeging. De veiling loopt nog.',
        'action' => 'Naar de kavel',
    ],

    'auction_lost' => [
        'subject' => 'De veiling van ":lot" is gesloten',
        'line' => ':lot is gegaan voor :price. Jouw bod was niet het hoogste.',
        'next' => 'Vergelijkbare kavels komen regelmatig voorbij. Volg een categorie of kijk wat er nu live staat.',
        'action' => 'Bekijk vergelijkbare kavels',
    ],

    'verification' => [
        'kyc_approved_subject' => 'Je identiteit is bevestigd',
        'kyc_approved_line' => 'Je identiteitscontrole is goedgekeurd. Bieden staat nu open; voor publiceren geldt bij een zakelijk account ook de bedrijfscontrole.',
        'kyc_rejected_subject' => 'Je identiteitscontrole is niet goedgekeurd',
        'kyc_rejected_line' => 'We konden je identiteit niet bevestigen. Bieden staat daardoor nog niet open. Je kunt het opnieuw proberen.',
        'kyb_approved_subject' => 'Je bedrijf is gecontroleerd',
        'kyb_approved_line' => 'De KvK-gegevens van je bedrijf zijn nagekeken en kloppen. Publiceren en uitbetalen staat nu open.',
        'kyb_rejected_subject' => 'De bedrijfscontrole is niet goedgekeurd',
        'kyb_rejected_line' => 'We konden de KvK-gegevens van je bedrijf niet bevestigen. Publiceren en uitbetalen staat daardoor nog niet open. Je kunt opnieuw aanvragen.',
        'reason' => 'Reden: :reason',
        'approved_action' => 'Naar de veilingen',
        'rejected_action' => 'Bekijk je verificatie',
    ],

    /*
     | Moderatie (sectie 8): elke gepubliceerde kavel wordt nagekeken.
     */
    'lot_moderated' => [
        'approved_subject' => '":lot" is nagekeken en in orde',
        'approved_line' => 'We hebben ":lot" bekeken. De kavel staat gewoon live; dit is alleen een bevestiging dat alles klopt.',
        'approved_action' => 'Naar de kavel',
        'rejected_subject' => '":lot" is offline gehaald',
        'rejected_line' => 'We hebben ":lot" na controle offline gehaald. Een lopende veiling is gesloten zonder winnaar en eventuele borgen zijn vrijgegeven.',
        'reason' => 'Reden: :reason',
        'rejected_next' => 'Klopt er iets niet of is het op te lossen? Pas de kavel aan en plaats hem opnieuw, of neem contact op.',
        'rejected_action' => 'Naar mijn kavels',
    ],

    /*
     | Alerts en volglijst (briefing sectie 3 en 8).
     */
    'alert_matches' => [
        'subject' => '{1} Nieuwe kavel voor je alert ":name"|[2,*] :count nieuwe kavels voor je alert ":name"',
        'line' => '{1} Er is een nieuwe kavel geplaatst die past bij ":name":|[2,*] Er zijn :count nieuwe kavels geplaatst die passen bij ":name":',
        'action' => 'Bekijk de kavels',
        'manage' => 'Alert aanpassen of pauzeren: :url',
    ],

    'watchlist_ending' => [
        'subject' => '":lot" sluit binnenkort',
        'line' => 'De veiling van :lot sluit om :ends. De stand is nu :price.',
        'action' => 'Naar de veiling',
    ],

    'order_message' => [
        'subject' => 'Nieuw bericht van :from over ":lot"',
        'line' => 'Bij bestelling :order schreef :from:',
        'action' => 'Naar de bestelling',
        'footer' => 'Antwoorden doe je op de bestelpagina, dan blijft alles bij elkaar.',
        'from' => ['buyer' => 'de koper', 'seller' => 'de verkoper', 'support' => 'Aucvy support'],
    ],

    'auction_withdrawn' => [
        'subject' => 'De veiling van ":lot" is beëindigd',
        'line' => 'Aucvy heeft ":lot" na controle offline gehaald. De veiling is beëindigd zonder winnaar; je bod vervalt.',
        'deposit' => 'Had je een borg vastgezet, dan is die vrijgegeven.',
        'action' => 'Bekijk andere veilingen',
    ],

    'team_joined' => [
        'subject' => ':name is bij je team gekomen',
        'line' => ':name heeft de uitnodiging aangenomen en werkt nu mee als :role.',
        'action' => 'Naar het team',
    ],

    'two_factor' => [
        'subject' => 'Je inlogcode: :code',
        'line' => 'Je inlogcode is :code. Hij is tien minuten geldig.',
        'warning' => 'Vroeg jij dit niet aan? Verander dan je wachtwoord.',
    ],

];
