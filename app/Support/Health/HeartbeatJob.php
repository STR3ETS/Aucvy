<?php

declare(strict_types=1);

namespace App\Support\Health;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Eén lege job per queue per minuut: wordt hij verwerkt, dan leeft de
 * worker. Zo ziet de systeempagina dat een worker stilstaat vóór een
 * bieder het merkt.
 */
final class HeartbeatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $queueName)
    {
        $this->onQueue($queueName);
    }

    public function handle(): void
    {
        Cache::forever(Health::workerKey($this->queueName), now()->toIso8601String());
    }
}
