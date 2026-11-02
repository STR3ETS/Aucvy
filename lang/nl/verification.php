<?php

declare(strict_types=1);

return [

    'link_sent' => 'We hebben je een nieuwe bevestigingslink gestuurd.',
    'reset_link_sent' => 'Als dit e-mailadres bij ons bekend is, staat er nu een herstel-link in je inbox.',
    'password_reset' => 'Je wachtwoord is gewijzigd. Je kunt inloggen.',

    'phone' => [
        'sms' => 'Je Aucvy-code is :code. Hij is :minutes minuten geldig. Deel hem met niemand.',
        'sent' => 'We hebben een code per sms gestuurd naar :phone.',
        'verified' => 'Je telefoonnummer is bevestigd.',
        'invalid' => 'Die code klopt niet of is verlopen.',
        'invalid_number' => 'Dat is geen geldig telefoonnummer. Gebruik bijvoorbeeld 06 12345678 of +32 470 12 34 56.',
        'too_many' => 'Je hebt te vaak een code aangevraagd. Probeer het over :minutes minuten opnieuw.',
        'too_many_tries' => 'Te veel foute pogingen. Vraag een nieuwe code aan.',
    ],

    'identity' => [
        'bank_verified' => 'Je identiteit is bevestigd via je bank. Je kunt bieden.',
        'bank_cancelled' => 'Je hebt de bevestiging bij je bank afgebroken. Probeer het opnieuw wanneer je wilt.',
        'bank_minor' => 'Bieden en verkopen kan vanaf 18 jaar.',
        'bank_failed' => 'De bevestiging bij je bank is niet gelukt. Probeer het opnieuw, of neem contact op.',
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
