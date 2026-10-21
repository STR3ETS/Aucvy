<?php

declare(strict_types=1);

/*
 | Het team van een bedrijfsaccount: rollen, uitnodigingen.
 */

return [

    'invited' => 'Uitnodiging verstuurd naar :email.',
    'role_changed' => 'De rol van :name is aangepast.',
    'removed' => ':name hoort niet meer bij dit bedrijf.',
    'revoked' => 'De uitnodiging voor :email is ingetrokken.',
    'joined' => 'Welkom bij :company. Je ziet nu de kavels van het hele team.',

    'errors' => [
        'already_member' => 'Dit adres hoort al bij je team.',
        'other_company' => 'Dit adres hoort al bij een ander bedrijfsaccount op Aucvy.',
        'other_company_self' => 'Je hoort al bij een ander bedrijf. Laat je daar eerst verwijderen.',
        'expired' => 'Deze uitnodiging is verlopen of al gebruikt. Vraag een nieuwe aan je collega.',
        'wrong_account' => 'Deze uitnodiging is voor :email. Log in met dat adres, of maak er een account mee.',
        'own_role' => 'Je eigen rol kun je niet wijzigen. Vraag het een andere eigenaar.',
        'remove_self' => 'Jezelf verwijderen kan niet. Vraag het een andere eigenaar.',
        'last_owner' => 'Er moet minstens één eigenaar overblijven. Maak eerst iemand anders eigenaar.',
    ],

    'mail' => [
        'subject' => 'Je bent uitgenodigd voor het team van :company op Aucvy',
        'greeting' => 'Hoi,',
        'line' => ':inviter nodigt je uit om bij :company te komen op Aucvy, als :role.',
        'action' => 'Uitnodiging bekijken',
        'account' => 'Heb je nog geen account? Maak er dan eerst een met :email en open daarna deze link opnieuw.',
        'expires' => 'De link is :days dagen geldig.',
    ],

];
