<?php

declare(strict_types=1);

/*
 | Risicosignalen voor beheer: wat de regel zag, in gewone taal.
 */

return [

    'self_bid' => ':name bood op een eigen kavel: ":lot"',
    'self_bid_body' => '{1} Eén bod vanuit het eigen account of bedrijf. Opdrijven van de prijs is niet toegestaan.|[2,*] :count biedingen vanuit het eigen account of bedrijf. Opdrijven van de prijs is niet toegestaan.',

    'shared_ip' => ':name bood op ":lot" vanaf hetzelfde adres als de verkoper',
    'shared_ip_body' => 'IP-adres :ip komt ook voor bij de verkoper. Kan een kantoornetwerk zijn; kijk naar de biedhistorie.',

    'non_payer' => ':name won vaker zonder te betalen',
    'non_payer_body' => ':count bestellingen in 90 dagen vervallen door een verlopen betaaltermijn.',

    'rejected_seller' => 'Kavels van :name worden vaker afgewezen',
    'rejected_seller_body' => ':count kavels in 90 dagen door moderatie offline gehaald.',

];
