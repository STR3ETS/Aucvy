<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Kostenopbouw
|--------------------------------------------------------------------------
|
| De regels zoals ze in het CostBreakdown-component verschijnen: op de
| kavelpagina vóór het bieden, bij koop-direct, en op de order.
|
*/

return [

    'lines' => [
        'hammer' => 'Bod',
        'buyer_premium' => 'Opgeld',
        'transport' => 'Transport',
        'vat' => 'Btw :rate%',
        'margin' => 'Btw',
        'fee' => 'Succesfee :rate%',
        'payout' => 'Uitbetaling',
        'total' => 'Totaal',
    ],

    'notes' => [
        // Zichtbaar maken dát er een margeregeling geldt is geen detail: het
        // scheelt een zakelijke koper 21% die hij niet kan terugvorderen.
        'margin' => 'Margeregeling - btw niet verrekenbaar',
        'pickup' => 'Zelf ophalen',
        'estimate' => 'Indicatie - definitief na bevestiging',
        'transport_unknown' => 'Transportprijs nog niet bekend',
    ],

];
