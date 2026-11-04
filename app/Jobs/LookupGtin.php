<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Lots\Scan\ProductLookup;
use App\Models\Lot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * EAN opzoeken op de queue ai, binnen het dagbudget van de Ivy-laag.
 */
final class LookupGtin implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $lotId, public readonly string $gtin)
    {
        $this->onQueue('ai');
    }

    public function handle(ProductLookup $lookup): void
    {
        $lot = Lot::query()->find($this->lotId);

        if ($lot === null) {
            return;
        }

        $lookup->applyTo($lot, $this->gtin, $lookup->lookup($this->gtin));
    }
}
