<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Privacy\ExportUserData;
use Illuminate\Console\Command;

/**
 * Het inzageverzoek uit hfst. 15: zet de export als job op de queue; de
 * gebruiker krijgt een mail met een tijdelijk getekende downloadlink.
 */
final class ExportUserDataCommand extends Command
{
    protected $signature = 'aucvy:export-user {email : Het e-mailadres van de gebruiker}';

    protected $description = 'Maakt de gegevensexport voor een gebruiker en mailt de downloadlink';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Geen gebruiker met dat e-mailadres.');

            return self::FAILURE;
        }

        ExportUserData::dispatch($user);

        $this->info('Export staat op de queue; de gebruiker krijgt een mail met de downloadlink.');

        return self::SUCCESS;
    }
}
