<?php

declare(strict_types=1);

namespace App\Distribution\Publishers;

use App\Models\LotDistribution;

/**
 * Eén contract per kanaal: plaatsen en offline halen. Zonder koppeling
 * doet de ManualPublisher het werk: het bericht staat klaar, de verkoper
 * plaatst zelf.
 */
interface ChannelPublisher
{
    public function publish(LotDistribution $distribution, string $body, string $visualUrl): PublishResult;

    public function withdraw(LotDistribution $distribution): void;
}
