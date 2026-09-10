<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Veilingen
|--------------------------------------------------------------------------
|
| De redenen waaróm een bod geweigerd wordt staan in enums.php, bij
| bid_rejection_reason. Hier staat wat daaromheen zit.
|
*/

return [

    'deposit' => [
        'provider_missing' => 'De borgreservering via de betaalprovider is nog niet ingericht - dit volgt met de derdengeldenkeuze.',
    ],

    'errors' => [
        // Zichtbaar voor een ontwikkelaar, niet voor een koper: als deze tekst
        // op een scherm belandt is er een client die de header vergeet.
        'idempotency_key_required' => 'Deze aanvraag mist een Idempotency-Key-header.',
        'too_many_attempts' => 'Te veel biedpogingen achter elkaar. Probeer het over een paar seconden opnieuw.',
    ],

    'state' => [
        'live' => 'Live',
        'closing' => 'Slotfase',
        'closed' => 'Gesloten',
    ],

    'viewer' => [
        'highest' => 'Je bent de hoogste bieder',
        'outbid' => 'Je bent overboden',
        'won' => 'Je hebt gewonnen',
        'lost' => 'Je hebt niet gewonnen',
    ],

    'extended' => '{1} :count× verlengd door biedingen in de slotfase|[2,*] :count× verlengd door biedingen in de slotfase',

];
