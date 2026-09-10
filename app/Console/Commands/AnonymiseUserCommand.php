<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Privacy\AnonymiseUser;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Het verwijderverzoek uit hfst. 15, uitgevoerd als anonimisering. Handmatig
 * te draaien door een medewerker; met --force zonder bevestigingsvraag.
 */
final class AnonymiseUserCommand extends Command
{
    protected $signature = 'aucvy:anonymise-user {email : Het e-mailadres van de gebruiker} {--force : Geen bevestigingsvraag}';

    protected $description = 'Anonimiseert een gebruiker; transacties blijven staan tot hun bewaartermijn';

    public function handle(AnonymiseUser $anonymise): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('Geen gebruiker met dat e-mailadres.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("{$user->name} ({$user->email}) anonimiseren? Dit is definitief.")) {
            return self::SUCCESS;
        }

        try {
            $anonymise->handle($user);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Gebruiker geanonimiseerd. Transactierijen blijven staan tot hun bewaartermijn.');

        return self::SUCCESS;
    }
}
