<?php

declare(strict_types=1);

/*
 | Delen en verspreiden (briefing nieuwe functies): deelkit, kanalen,
 | social push en de deling door een koper.
 */
return [

    'channels' => [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'story' => 'Instagram story',
        'linkedin' => 'LinkedIn',
        'marktplaats' => 'Marktplaats',
        'whatsapp' => 'WhatsApp',
        'mail' => 'E-mail',
        'link' => 'Link',
        'system' => 'Deelmenu van het apparaat',
    ],

    'status' => [
        'draft' => 'Concept',
        'scheduled' => 'Ingepland',
        'posting' => 'Wordt geplaatst',
        'ready' => 'Staat klaar om te plaatsen',
        'posted' => 'Geplaatst',
        'failed' => 'Mislukt',
        'offline' => 'Offline',
    ],

    'moments' => [
        'kit' => 'Deelkit',
        'start' => 'Bij de start',
        'day_before' => 'Dag voor sluiting',
        'last_hour' => 'Laatste uur',
        'close' => 'Bij sluiting',
    ],

    'formats' => [
        'square' => 'Vierkant (1080 × 1080)',
        'portrait' => 'Staand (1080 × 1350)',
        'landscape' => 'Liggend (1200 × 630)',
    ],

    'reactions' => [
        'interested' => 'Interessant',
        'not_interested' => 'Niet interessant',
        'text' => 'Reactie',
    ],

    'connection_status' => [
        'unavailable' => 'Koppeling volgt',
        'none' => 'Niet gekoppeld',
        'pending' => 'Koppelen gestart',
        'connected' => 'Gekoppeld',
        'expired' => 'Verlopen, opnieuw koppelen',
        'error' => 'Storing, opnieuw koppelen',
    ],

    'connection_help' => [
        'facebook' => 'Plaatsen op je bedrijfspagina via de Graph API. Niet op een persoonlijk profiel; de app-review bij Meta duurt één tot drie weken.',
        'instagram' => 'Foto en carrousel plaatsen via Content Publishing. Alleen professionele accounts; reken met 50 berichten per dag.',
        'linkedin' => 'Plaatsen namens jou via Share on LinkedIn. Bedrijfspagina\'s vragen een aparte goedkeuring.',
        'marktplaats' => 'Plaatsen via de Marktplaats-API vraagt partnerschap en betaald adverteren. Tot die tijd: de deelkit, één advertentie per kavel.',
    ],

];
