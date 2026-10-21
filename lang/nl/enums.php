<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Enumlabels
|--------------------------------------------------------------------------
|
| Code, kolommen en enumwaarden zijn Engels; alles wat de gebruiker leest is
| Nederlands (briefing hfst. 4). De sleutel is de snake_case-klassenaam van
| de enum, de subsleutel de enumwaarde.
|
*/

return [

    'user_type' => [
        'private' => 'Particulier',
        'business' => 'Zakelijk',
    ],

    'kyc_status' => [
        'unverified' => 'Niet geverifieerd',
        'pending' => 'Verificatie loopt',
        'verified' => 'Geverifieerd',
        'rejected' => 'Afgewezen',
    ],

    'company_role' => [
        'owner' => 'Eigenaar',
        'admin' => 'Beheerder',
        'editor' => 'Redacteur',
        'finance' => 'Financieel',
    ],

    'company_role_description' => [
        'owner' => 'Alles: kavels, financiën, team en bedrijfsgegevens. Er is altijd minstens één eigenaar.',
        'admin' => 'Kavels en financiën, en het team beheren. Kan geen eigenaar verwijderen.',
        'editor' => 'Kavels plaatsen en bewerken, ook die van collega\'s. Ziet geen financiën.',
        'finance' => 'Financiën en uitbetalingen van het hele bedrijf. Bewerkt geen kavels van collega\'s.',
    ],

    'sale_state' => [
        'draft' => 'Concept',
        'scheduled' => 'Gepland',
        'live' => 'Loopt',
        'closed' => 'Gesloten',
        'cancelled' => 'Geannuleerd',
    ],

    'company_plan' => [
        'start' => 'Start',
        'pro' => 'Pro',
        'enterprise' => 'Enterprise',
    ],

    'home_variant' => [
        'a' => 'Ontwerp A (oud)',
        'b' => 'Ontwerp B (v4)',
        'c' => 'Ontwerp C (hybride)',
    ],

    'lot_status' => [
        'draft' => 'Concept',
        'pending_review' => 'In beoordeling',
        'scheduled' => 'Ingepland',
        'live' => 'Live',
        'closing' => 'Slotfase',
        'paused' => 'Gepauzeerd',
        'sold' => 'Verkocht',
        'unsold' => 'Niet verkocht',
        'cancelled' => 'Ingetrokken',
    ],

    // Zoals ze in de filters van preview v4 staan.
    'lot_condition' => [
        'new' => 'Nieuw',
        'like_new' => 'Zo goed als nieuw',
        'good' => 'Gebruikt - goed',
        'used' => 'Gebruikt',
        'defective' => 'Voor onderdelen',
    ],

    'sale_type' => [
        'auction' => 'Veiling',
        'dutch' => 'Afslag',
        'buy_now' => 'Koop direct',
    ],

    'vat_regime' => [
        'margin' => 'Margeregeling',
        'vat_21' => '21% btw',
    ],

    'value_confidence' => [
        'low' => 'Lage zekerheid',
        'medium' => 'Redelijke zekerheid',
        'high' => 'Hoge zekerheid',
    ],

    'media_type' => [
        'image' => 'Foto',
        'video' => 'Video',
        'tour360' => '360°-tour',
    ],

    'document_kind' => [
        'invoice' => 'Factuur',
        'warranty' => 'Garantiebewijs',
        'ownership' => 'Eigendomsbewijs',
        'manual' => 'Handleiding',
        'other' => 'Overig',
    ],

    'bid_type' => [
        'manual' => 'Handmatig',
        'proxy' => 'Automatisch',
        'buy_now' => 'Koop direct',
        'dutch' => 'Afgemijnd',
    ],

    'bid_status' => [
        'winning' => 'Hoogste bod',
        'outbid' => 'Overboden',
        'rejected' => 'Geweigerd',
    ],

    /*
     | Waarom een bod geweigerd is. Deze teksten staan letterlijk in het
     | biedpaneel, dus geen enkele mag "er ging iets mis" betekenen: elk
     | faalgeval hoort de gebruiker te vertellen wat er nú te doen valt.
     */
    'bid_rejection_reason' => [
        'auction_not_open' => 'Deze veiling loopt niet meer.',
        'auction_ended' => 'De veiling is zojuist gesloten.',
        'own_lot' => 'Je kunt niet bieden op je eigen kavel.',
        'same_company' => 'Bieden op een kavel van je eigen bedrijf kan niet.',
        'not_verified' => 'Rond eerst je verificatie af - dat duurt twee minuten.',
        'blocked' => 'Dit account is geblokkeerd. Neem contact op met Aucvy als je denkt dat dit niet klopt.',
        'deposit_required' => 'Voor dit bedrag vragen we eerst een borg, die je terugkrijgt als je niet wint.',
        'too_low' => 'Dit bod is lager dan de volgende biedstap.',
        'already_highest' => 'Je bent al de hoogste bieder.',
        'auto_bid_not_increasing' => 'Een maximum kan alleen omhoog, niet omlaag.',
        'price_moved' => 'De prijs is inmiddels verder gezakt - we tonen je de actuele prijs.',
        'buy_now_unavailable' => 'Koop direct is vervallen: er is boven de vaste prijs geboden.',
        'sale_type_mismatch' => 'Deze kavel wordt op een andere manier verkocht.',
    ],

    'auction_event_type' => [
        'bid_placed' => 'Bod geplaatst',
        'outbid' => 'Overboden',
        'extended' => 'Verlengd',
        'price_dropped' => 'Prijs gedaald',
        'buy_now_disabled' => 'Koop direct vervallen',
        'closed' => 'Gesloten',
        'cancelled' => 'Ingetrokken',
    ],

    'order_status' => [
        'awaiting_payment' => 'Wacht op betaling',
        'paid' => 'Betaald',
        'ready_for_pickup' => 'Klaar om op te halen',
        'shipped' => 'Onderweg',
        'delivered' => 'Geleverd',
        'completed' => 'Afgerond',
        'disputed' => 'Geschil',
        'cancelled' => 'Geannuleerd',
    ],

    'payment_status' => [
        'pending' => 'In behandeling',
        'paid' => 'Betaald',
        'failed' => 'Mislukt',
        'expired' => 'Verlopen',
        'refunded' => 'Terugbetaald',
    ],

    'payout_status' => [
        'scheduled' => 'Ingepland',
        'held' => 'Aangehouden',
        'released' => 'Vrijgegeven',
        'paid' => 'Uitbetaald',
        'failed' => 'Mislukt',
    ],

    'deposit_status' => [
        'pending' => 'In behandeling',
        'authorised' => 'Gereserveerd',
        'released' => 'Vrijgegeven',
        'captured' => 'Verrekend',
        'failed' => 'Mislukt',
    ],

    'dispute_status' => [
        'reported' => 'Gemeld',
        'seller_responding' => 'Verkoper reageert',
        'under_review' => 'In beoordeling',
        'resolved' => 'Opgelost',
        'rejected' => 'Afgewezen',
    ],

    'viewing_status' => [
        'requested' => 'Aangevraagd',
        'confirmed' => 'Bevestigd',
        'cancelled' => 'Geannuleerd',
        'completed' => 'Geweest',
    ],

    'ai_agent' => [
        'ivy' => 'Ivy',
        'atlas' => 'Atlas',
        'nova' => 'Nova',
        'finn' => 'Finn',
        'flow' => 'Flow',
        'lex' => 'Lex',
    ],

    'ai_agent_speciality' => [
        'ivy' => 'coördineert',
        'atlas' => 'markt',
        'nova' => 'marketing',
        'finn' => 'geld',
        'flow' => 'logistiek',
        'lex' => 'compliance',
    ],

    'ai_run_status' => [
        'queued' => 'In wachtrij',
        'running' => 'Bezig',
        'completed' => 'Klaar',
        'failed' => 'Mislukt',
        'skipped' => 'Overgeslagen',
    ],

    'insight_severity' => [
        'info' => 'Ter info',
        'attention' => 'Vraagt aandacht',
        'urgent' => 'Urgent',
    ],

];
