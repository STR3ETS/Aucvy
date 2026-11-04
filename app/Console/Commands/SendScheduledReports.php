<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Admin\Reports;
use App\Models\ReportSchedule;
use App\Notifications\ReportReady;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Vaste rapporten per mail: wekelijks over de vorige week, maandelijks
 * over de vorige maand, met een link die na een dag verloopt.
 */
final class SendScheduledReports extends Command
{
    protected $signature = 'aucvy:send-reports';

    protected $description = 'Verstuurt de ingeplande rapporten van het control center';

    public function handle(Reports $reports): int
    {
        $sent = 0;

        foreach (ReportSchedule::query()->get() as $schedule) {
            if (! $schedule->isDue()) {
                continue;
            }

            [$from, $to] = $schedule->frequency === 'monthly'
                ? [CarbonImmutable::now()->subMonth()->startOfMonth(), CarbonImmutable::now()->subMonth()->endOfMonth()]
                : [CarbonImmutable::now()->subWeek()->startOfWeek(), CarbonImmutable::now()->subWeek()->endOfWeek()];

            $path = $reports->store($schedule->report_key, $from, $to);

            Notification::route('mail', (array) $schedule->recipients)
                ->notify(new ReportReady($schedule->report_key, $from, $to, $reports->signedUrl($path)));

            $schedule->forceFill(['last_sent_at' => now()])->save();
            $sent++;
        }

        $this->info("{$sent} rapporten verstuurd.");

        return self::SUCCESS;
    }
}
