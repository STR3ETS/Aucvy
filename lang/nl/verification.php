<?php

declare(strict_types=1);

return [

    'link_sent' => 'We hebben je een nieuwe bevestigingslink gestuurd.',
    'reset_link_sent' => 'Als dit e-mailadres bij ons bekend is, staat er nu een herstel-link in je inbox.',
    'password_reset' => 'Je wachtwoord is gewijzigd. Je kunt inloggen.',

    'identity' => [
        'pending' => 'Je aanvraag is binnen. Een medewerker kijkt ernaar; meestal binnen een werkdag. Je krijgt een mail met de uitkomst.',
        'rejected' => 'Niet goedgekeurd: :reason Je kunt het opnieuw proberen.',
        'rejected_no_reason' => 'Niet goedgekeurd. Je kunt het opnieuw proberen.',
    ],

    'kvk' => [
        'pending' => 'Aangevraagd. We kijken de KvK-gegevens na; je krijgt een mail met de uitkomst.',
        'rejected' => 'Niet goedgekeurd: :reason Vraag opnieuw aan als dat is opgelost.',
    ],

    'ubo' => [
        'saved' => 'De UBO-verklaring is opgeslagen. Compliance kijkt hem na bij de KvK-beoordeling.',
        'errors' => [
            'empty' => 'Vul minstens één persoon in.',
            'birth_year' => 'Een geboortejaar tussen 1900 en zestien jaar geleden.',
            'share' => 'Bij 25% of meer hoort een percentage tussen 25 en 100.',
        ],
    ],

    'errors' => [
        'email_first' => 'Bevestig eerst je e-mailadres - dat is de eerste stap.',
        'business_only' => 'De KVK-stap is alleen voor zakelijke accounts.',
    ],

];
