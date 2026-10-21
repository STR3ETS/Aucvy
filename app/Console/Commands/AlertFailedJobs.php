<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Notifications\FailedJobsAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Het dead-letter-alarm (briefing sectie 8): zijn er sinds de vorige
 * controle jobs mislukt, dan gaat er één mail naar de supportinbox met de
 * aantallen per job. Geen herhaling zolang er niets nieuws bijkomt. De
 * eerste keer zet het alleen het ijkpunt; wat er al lag is dan bekend.
 */
final class AlertFailedJobs extends Command
{
    public const WATERMARK = 'queue_alert_last_id';

    protected $signature = 'queue:alert';

    protected $description = 'Mailt support als er sinds de vorige controle jobs zijn mislukt';

    public function handle(): int
    {
        $lastSeen = Cache::get(self::WATERMARK);
        $maxId = (int) DB::table('failed_jobs')->max('id');

        // Zonder ijkpunt (eerste keer, of de cache is geleegd) telt het laatste
        // kwartier, de tijd tussen twee runs. Zo gaat er geen melding verloren.
        $byJob = DB::table('failed_jobs')
            ->when($lastSeen !== null, fn ($query) => $query->where('id', '>', (int) $lastSeen), fn ($query) => $query->where('failed_at', '>=', now()->subMinutes(15)))
            ->selectRaw("json_unquote(json_extract(payload, '$.displayName')) as job, count(*) as total")
            ->groupBy('job')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => ['job' => (string) ($row->job ?? 'onbekend'), 'total' => (int) $row->total]);

        if ($byJob->isEmpty()) {
            Cache::forever(self::WATERMARK, $maxId);
            $this->info('Geen nieuwe mislukte jobs.');

            return self::SUCCESS;
        }

        Notification::route('mail', (string) config('aucvy.support.email'))
            ->notify(new FailedJobsAlert($byJob->all(), DB::table('failed_jobs')->count()));

        // Pas na het versturen schuift het ijkpunt op: mislukt de mail, dan komt het de volgende keer alsnog.
        Cache::forever(self::WATERMARK, $maxId);

        $this->info($byJob->sum('total').' nieuwe mislukte jobs gemeld.');

        return self::SUCCESS;
    }
}
