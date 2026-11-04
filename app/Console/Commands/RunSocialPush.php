<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DistributeLotToChannel;
use App\Models\LotDistribution;
use Illuminate\Console\Command;

/**
 * Elke vijf minuten: de goedgekeurde berichten waarvan het moment is
 * aangebroken op de queue zetten. Het bod wordt pas in de job ingevuld.
 */
final class RunSocialPush extends Command
{
    protected $signature = 'aucvy:social-push';

    protected $description = 'Plaatst de goedgekeurde social-pushberichten waarvan het moment is aangebroken';

    public function handle(): int
    {
        $due = LotDistribution::query()->due()->get();

        foreach ($due as $distribution) {
            $distribution->forceFill(['status' => LotDistribution::POSTING])->save();
            DistributeLotToChannel::dispatch($distribution->id);
        }

        $this->info("{$due->count()} berichten op de queue gezet.");

        return self::SUCCESS;
    }
}
