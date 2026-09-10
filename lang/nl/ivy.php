<?php

declare(strict_types=1);

/*
 | Wat de UI naast een Ivy-uitkomst zet. De teksten horen bij de
 | ConfidencePolicy (hfst. 8): bij lage zekerheid geen exacte bedragen als
 | advies - de formulering houdt daar al rekening mee.
 */

return [

    'confidence' => [
        'low' => 'Lage zekerheid - zie dit als een eerste richting en controleer alles zelf.',
        'medium' => 'Redelijke zekerheid - loop de ingevulde velden even na.',
        'high' => 'Hoge zekerheid - gebaseerd op duidelijk herkenbaar materiaal.',
    ],

    'tasks' => [
        'recognise_lot' => 'Object herkennen',
        'draft_listing' => 'Advertentie schrijven',
        'estimate_value' => 'Waarde schatten',
    ],

];
