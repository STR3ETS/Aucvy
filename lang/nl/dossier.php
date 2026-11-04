<?php

declare(strict_types=1);

/*
 | Het kaveldossier: één tijdlijn per kavel (briefing nieuwe functies,
 | 3 november 2026). Elke regel heeft een bron en een type.
 */
return [

    'sources' => [
        'lot' => 'Kavel',
        'auction' => 'Veiling',
        'order' => 'Order en geld',
        'message' => 'Berichten',
        'mail' => 'Mail',
        'document' => 'Documenten',
        'distribution' => 'Verspreiding',
        'live' => 'Live',
    ],

    'tabs' => [
        'overzicht' => 'Overzicht',
        'berichten' => 'Berichten',
        'documenten' => 'Documenten',
        'verspreiding' => 'Verspreiding',
        'dossier' => 'Dossier',
    ],

    'types' => [
        'lot' => [
            'imported' => 'Kavel geïmporteerd',
            'submitted' => 'Ingediend voor controle',
            'published' => 'Kavel staat live',
            'returned' => 'Teruggestuurd door Ivy: er ontbreekt iets',
            'escalated' => 'Bij een medewerker gelegd',
            'approved' => 'Goedgekeurd door een medewerker',
            'rejected' => 'Afgewezen en offline gehaald',
            'updated' => 'Kavel gewijzigd',
            'media_changed' => "Foto's gewijzigd",
            'paused' => 'Kavel gepauzeerd',
            'resumed' => 'Kavel hervat',
        ],
        'auction' => [
            'first_bid' => 'Eerste bod: :amount',
            'bid' => 'Bod: :amount',
            'outbid' => 'Je bent overboden, de stand is :amount',
            'extended' => 'Veiling verlengd door een laat bod',
            'sold' => 'Verkocht voor :amount',
            'unsold' => 'Gesloten zonder koper',
            'withdrawn' => 'Veiling ingetrokken',
            'cancelled' => 'Veiling geannuleerd',
        ],
        'order' => [
            'created' => 'Order aangemaakt: :amount',
            'awaiting_payment' => 'Wacht op betaling',
            'paid' => 'Betaald',
            'ready_for_pickup' => 'Klaar om op te halen',
            'shipped' => 'Verzonden',
            'delivered' => 'Geleverd',
            'completed' => 'Afgerond',
            'disputed' => 'Geschil geopend',
            'cancelled' => 'Order geannuleerd',
            'payment_attention' => 'Betaling vraagt aandacht',
            'payout_released' => 'Uitbetaling vrijgegeven',
            'payout_corrected' => 'Uitbetaling gecorrigeerd',
            'deposit_pending' => 'Borg wacht op afschrijving',
            'review_held' => 'Review vastgehouden',
            'review_reported' => 'Review gemeld',
            'review_removed' => 'Review verwijderd',
        ],
        'message' => [
            'question' => 'Vraag gesteld',
            'answer' => 'Vraag beantwoord',
            'message' => 'Bericht verstuurd',
            'held' => 'Bericht vastgehouden door de bot',
            'masked' => 'Contactgegevens afgeschermd',
            'released' => 'Bericht vrijgegeven door een medewerker',
            'operator_joined' => 'Een medewerker leest mee',
            'operator_reply' => 'Reactie van Aucvy',
            'published' => 'Antwoord bij de kavel gezet',
        ],
        'mail' => [
            'sent' => 'Mail verstuurd: :subject',
            'delivered' => 'Mail afgeleverd: :subject',
            'deferred' => 'Mail vertraagd: :subject',
            'failed' => 'Mail gebounced: :subject',
            'complained' => 'Mail als spam gemeld: :subject',
        ],
        'document' => [
            'added' => 'Document toegevoegd: :kind',
            'checked' => 'Document gecontroleerd door Lex: :kind',
            'viewed' => 'Document bekeken: :kind',
            'visibility' => 'Zichtbaarheid gewijzigd: :kind',
        ],
        'distribution' => [
            'posted' => 'Geplaatst op :channel',
            'failed' => 'Plaatsen op :channel mislukt',
            'withdrawn' => 'Offline gehaald op :channel',
            'shared' => 'Gedeeld via :channel',
            'opened' => 'Gedeelde link geopend',
        ],
        'live' => [
            'started' => 'Uitzending gestart',
            'ended' => 'Uitzending gestopt (:count kijkers op het hoogtepunt)',
        ],
    ],

];
