<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Automations\AutomationEngine;
use Illuminate\Console\Command;

/**
 * Elk kwartier: nieuwe runs inschrijven en de stappen verwerken waarvan
 * de tijd is aangebroken.
 */
final class RunMailAutomations extends Command
{
    protected $signature = 'aucvy:automations';

    protected $description = 'Verwerkt de mailreeksen (betaalherinnering, concept, eerste kavel, slapende koper)';

    public function handle(AutomationEngine $engine): int
    {
        $result = $engine->tick();

        $this->info("{$result['enrolled']} ingeschreven, {$result['processed']} verwerkt, {$result['stopped']} gestopt.");

        return self::SUCCESS;
    }
}
