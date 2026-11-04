<?php

declare(strict_types=1);

namespace App\Distribution\Publishers;

use App\Models\LotDistribution;

/**
 * Geen koppeling: het bericht en het beeld staan klaar, de verkoper
 * plaatst zelf en vinkt af. Dit dekt elk kanaal op de dag van livegang.
 */
final class ManualPublisher implements ChannelPublisher
{
    public function publish(LotDistribution $distribution, string $body, string $visualUrl): PublishResult
    {
        return PublishResult::ready();
    }

    public function withdraw(LotDistribution $distribution): void
    {
        // Niets te doen op het kanaal zelf; de status gaat lokaal naar offline.
    }
}
