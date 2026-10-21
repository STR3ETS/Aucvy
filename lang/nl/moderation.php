<?php

declare(strict_types=1);

/*
 | Meldingen in de backoffice na een moderatiebesluit.
 */

return [

    'approved' => '":lot" is goedgekeurd. De verkoper krijgt een bevestiging.',
    'rejected' => '":lot" is offline gehaald. De verkoper krijgt de reden per mail.',

    'errors' => [
        'not_reviewable' => 'Deze kavel staat niet (meer) online; er valt niets te beoordelen.',
        'already_reviewed' => 'Deze kavel is al beoordeeld.',
    ],

];
