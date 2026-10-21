<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Notifications\SystemAlert;
use App\Support\Health\Health;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * De systeemcontrole voor de cron: exitcode 1 als iets fout staat, en met
 * --notify één mail naar support per falende controle per uur. Bewust
 * zonder wachtrij: als de worker het probleem is, moet dit toch aankomen.
 */
final class SystemHealth extends Command
{
    protected $signature = 'system:health {--notify : Mail support bij een falende controle}';

    protected $description = 'Controleert scheduler, workers, wachtrij, Ivy-budget, schijf en instellingen';

    public function handle(Health $health): int
    {
        $checks = $health->checks();

        foreach ($checks as $check) {
            $mark = match ($check['status']) {
                'ok' => '  ok ', 'warn' => ' let op', default => ' FOUT'
            };
            $this->line(sprintf('[%s] %s: %s', trim($mark), $check['label'], $check['value']));

            if ($check['hint'] !== null) {
                $this->line('        '.$check['hint']);
            }
        }

        $failing = array_values(array_filter($checks, fn (array $check): bool => $check['status'] === 'fail'));

        if ($failing !== [] && $this->option('notify')) {
            $fresh = array_values(array_filter($failing, fn (array $check): bool => Cache::add('system_alert.'.$check['key'], now()->toIso8601String(), now()->addHour())));

            if ($fresh !== []) {
                Notification::route('mail', (string) config('aucvy.support.email'))->notify(new SystemAlert($fresh));
                $this->info(count($fresh).' melding(en) naar support gemaild.');
            }
        }

        return $failing === [] ? self::SUCCESS : self::FAILURE;
    }
}
