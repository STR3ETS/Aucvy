<?php

declare(strict_types=1);

/*
 | Ivy als gesprekspartner (Ivy-briefing).
 */
return [

    /*
    | Het vak van elke specialist, voor het systeembericht en de begroeting
    | in het paneel. Gewone woorden: de klant leest ze ook.
    */
    'duties' => [
        'atlas' => 'de markt: wat een kavel ongeveer waard is, vergelijkbare verkopen en de interesse van kopers',
        'nova' => 'marketing: de advertentietekst, de foto\'s en het verspreiden van een kavel',
        'finn' => 'geld: betalingen, de succesfee, facturen en uitbetalingen',
        'flow' => 'logistiek: bezichtigen, ophalen, transport en levering',
        'lex' => 'documenten en regels: het eigendomsbewijs, verificatie en de voorwaarden',
    ],

    'hello' => [
        'atlas' => 'Ik ben Atlas, de AI-specialist voor de markt. Ik vertel je wat een kavel ongeveer waard is en hoeveel interesse er is.',
        'nova' => 'Ik ben Nova, de AI-specialist voor marketing. Ik help met je advertentietekst, je foto\'s en het verspreiden van je kavel.',
        'finn' => 'Ik ben Finn, de AI-specialist voor geld. Ik help met betalingen, de succesfee, facturen en uitbetalingen.',
        'flow' => 'Ik ben Flow, de AI-specialist voor logistiek. Ik help met bezichtigen, ophalen, transport en levering.',
        'lex' => 'Ik ben Lex, de AI-specialist voor documenten en regels. Ik help met je eigendomsbewijs, verificatie en de voorwaarden.',
    ],

    'roles' => [
        'visitor' => 'bezoeker',
        'buyer' => 'koper',
        'seller' => 'verkoper',
    ],

    'proposals' => [
        'bid' => 'Bod',
        'auto_bid' => 'Automatisch bieden',
        'dutch_accept' => 'Afmijnen',
        'buy_now' => 'Koop direct',
        'reserve_change' => 'Reserve verlagen',
        'publish' => 'Publiceren',
    ],

    'proposal_status' => [
        'proposed' => 'klaargezet',
        'shown' => 'wacht op je ja',
        'confirmed' => 'bevestigd',
        'executed' => 'uitgevoerd',
        'price_changed' => 'prijs veranderd, opnieuw bevestigen',
        'expired' => 'verlopen',
        'rejected' => 'niet uitgevoerd',
        'cancelled' => 'geannuleerd',
    ],

    'proposal' => [
        'executed' => 'Gedaan.',
        'closed' => 'De veiling is gesloten; er is niets geboden.',
        'expired' => 'Dit voorstel is verlopen.',
        'rejected' => 'Dit voorstel is niet uitgevoerd.',
        'cancelled' => 'Dit voorstel is geannuleerd.',
        'confirmed' => 'Wordt uitgevoerd.',
    ],

    'bid' => [
        'price_changed' => 'Het bod is net veranderd. Bevestig opnieuw met het actuele bedrag.',
        'rejected' => 'Het bod is geweigerd.',
    ],

    'modes' => [
        'notify' => 'Alleen melden',
        'ask' => 'Eerst vragen',
        'auto' => 'Automatisch tot limiet',
    ],

    'alerts' => [
        'outbid' => ['title' => 'Overboden'],
        'new_bid' => ['title' => 'Nieuw bod'],
        'won' => ['title' => 'Gewonnen'],
        'lost' => ['title' => 'Niet gewonnen'],
        'progress' => ['title' => 'Voortgang'],
        'interest' => ['title' => 'Interesse'],
        'reserve_not_met' => ['title' => 'Jouw beslissing'],
        'draft_idle' => ['title' => 'Nog te doen'],
        'sold' => ['title' => 'Verkocht'],
        'unsold' => ['title' => 'Niet verkocht'],
        'payment_received' => ['title' => 'Betaling binnen'],
        'payout' => ['title' => 'Uitbetaling'],
    ],

];
