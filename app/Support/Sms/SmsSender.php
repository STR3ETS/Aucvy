<?php

declare(strict_types=1);

namespace App\Support\Sms;

/**
 * Een sms versturen (besluit Raph, 2 november 2026: "sms-dienst koppelen
 * kan"). De partij is nog open (vervolgvraag V3); de rest van Aucvy kent
 * alleen dit contract, zodat de keuze één nieuwe klasse is.
 */
interface SmsSender
{
    /**
     * @param  string  $to  Telefoonnummer in E.164, bijvoorbeeld +31612345678.
     */
    public function send(string $to, string $message): void;
}
