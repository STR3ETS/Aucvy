<?php

declare(strict_types=1);

/*
 | Berichten tussen koper en verkoper (briefing nieuwe functies,
 | 3 november 2026). De moderator-bot werkt in twee lagen: vaste regels in
 | PHP (hier de woordenlijsten) en Ivy voor wat alleen met lezen te zien is.
 */
return [

    // Versie van de regels; staat bij elke botbeslissing.
    'rule_version' => '1',

    // Zonder keuze van Raph (besluit 1): contactgegevens afschermen tot er
    // betaald is. Daarna mag het, voor het ophalen.
    'mask_contact_before_payment' => true,

    // Een gesprek starten vraagt een bevestigd e-mailadres en telefoonnummer,
    // dezelfde drempel als bieden tot 250 euro.
    'require_verified_phone' => true,

    // Ongelezen: na zoveel minuten een push, daarna een mail.
    'unread_push_minutes' => 5,
    'unread_mail_minutes' => 30,

    // Buiten het platform om: tegenhouden, naar een medewerker.
    'off_platform' => [
        'contant', 'cash', 'tikkie', 'buiten aucvy', 'buiten het platform', 'buiten de site',
        'zonder aucvy', 'zonder de site', 'rechtstreeks overmaken', 'direct overmaken', 'zwart',
        'geen fee', 'zonder commissie', 'onder de tafel', 'bankrekening', 'overboeking',
    ],

    // Biedmanipulatie: naar een medewerker en een signaal voor Risk.
    'bid_manipulation' => [
        'bied niet tegen', 'niet tegen elkaar bieden', 'niet overbieden', 'laat je vriend', 'opbieden',
        'bied jij niet', 'ik bied niet als jij', 'verdelen we', 'samen bieden',
    ],

    // Een andere afspraak dan de order: laten staan, Ivy stelt de juiste knop voor.
    'different_deal' => [
        'korting', 'euro eraf', 'eraf doen', 'andere prijs', 'lagere prijs', 'zonder garantie',
        'andere datum', 'ophalen op', 'kom ophalen', 'afhalen op', 'levering op', 'bezorgen op',
    ],

    // Phishing: verkorte links en inlogpagina's; een link is nooit klikbaar.
    'short_link_hosts' => ['bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'is.gd', 'cutt.ly', 'rb.gy', 'shorturl.at', 'tiny.cc'],
    'phishing' => ['log in via', 'inloggen via', 'wachtwoord', 'verifieer je account', 'je account is geblokkeerd'],

    // Bijlagen: foto of PDF, via de bestaande upload.
    'attachment_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    'attachment_max_kb' => 10_240,

];
