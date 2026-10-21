<?php

declare(strict_types=1);

namespace App\Support\Health;

use App\Models\AiRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * De systeemgezondheid zonder Horizon of Sentry (briefing sectie 8, voor de
 * Plesk-hosting): hartslag van scheduler en workers, wachtrij, AI-budget,
 * schijfruimte, instellingen die op productie niet mogen, en de laatste
 * fouten uit het logbestand. Elke controle is ok, let op of fout.
 *
 * @phpstan-type Check array{key: string, label: string, status: 'ok'|'warn'|'fail', value: string, hint: string|null}
 */
final class Health
{
    public const QUEUES = ['default', 'realtime', 'ai'];

    public const SCHEDULER_KEY = 'heartbeat.scheduler';

    public function __construct(private readonly ?string $logPath = null) {}

    public static function workerKey(string $queue): string
    {
        return 'heartbeat.worker.'.$queue;
    }

    /**
     * @return list<Check>
     */
    public function checks(): array
    {
        $checks = [$this->scheduler()];

        foreach (self::QUEUES as $queue) {
            $checks[] = $this->worker($queue);
        }

        return [...$checks, $this->queue(), $this->failedJobs(), $this->ai(), $this->disk(), $this->settings(), $this->errors()];
    }

    /**
     * @param  list<Check>  $checks
     * @return 'ok'|'warn'|'fail'
     */
    public static function overall(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        return in_array('fail', $statuses, true) ? 'fail' : (in_array('warn', $statuses, true) ? 'warn' : 'ok');
    }

    /**
     * De laatste foutregels uit het log, nieuwste eerst.
     *
     * @return list<array{at: string, level: string, message: string}>
     */
    public function recentErrors(int $limit = 20): array
    {
        $path = $this->logPath ?? $this->newestLog();

        if ($path === null || ! is_file($path)) {
            return [];
        }

        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');
        fseek($handle, max(0, $size - 400_000));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[T ][\d:]+)[^\]]*\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $tail, $matches, PREG_SET_ORDER);

        return array_values(array_map(fn (array $match): array => [
            'at' => $match[1],
            'level' => $match[2],
            'message' => mb_substr(trim($match[3]), 0, 300),
        ], array_slice(array_reverse($matches), 0, $limit)));
    }

    private function scheduler(): array
    {
        $seen = $this->seen(self::SCHEDULER_KEY);

        return $this->heartbeat('scheduler', 'Scheduler', $seen, 2, 10, 'Zet in de cron van Plesk elke minuut: php artisan schedule:run.');
    }

    private function worker(string $queue): array
    {
        $seen = $this->seen(self::workerKey($queue));

        return $this->heartbeat('worker.'.$queue, 'Worker '.$queue, $seen, 3, 15, 'Hoort te draaien als php artisan queue:work database --queue='.$queue.' (of één worker voor alle drie), onder supervisor of een Plesk-taak die hem herstart.');
    }

    private function heartbeat(string $key, string $label, ?CarbonImmutable $seen, int $okMinutes, int $warnMinutes, string $hint): array
    {
        if ($seen === null) {
            return ['key' => $key, 'label' => $label, 'status' => 'fail', 'value' => 'nooit gezien', 'hint' => $hint];
        }

        $minutes = (int) $seen->diffInMinutes(now());
        $status = $minutes < $okMinutes ? 'ok' : ($minutes < $warnMinutes ? 'warn' : 'fail');

        return ['key' => $key, 'label' => $label, 'status' => $status, 'value' => 'laatst gezien '.$seen->diffForHumans(), 'hint' => $status === 'ok' ? null : $hint];
    }

    private function queue(): array
    {
        $pending = (int) DB::table('jobs')->count();
        $lagging = (int) DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<', now()->subMinutes(2)->timestamp)->count();

        $status = $lagging > 0 ? ($lagging > 50 ? 'fail' : 'warn') : ($pending > 500 ? 'warn' : 'ok');

        return [
            'key' => 'queue',
            'label' => 'Wachtrij',
            'status' => $status,
            'value' => $pending.' in de wacht'.($lagging > 0 ? ', '.$lagging.' langer dan twee minuten' : ''),
            'hint' => $status === 'ok' ? null : 'Jobs blijven liggen: controleer de worker en Reverb.',
        ];
    }

    private function failedJobs(): array
    {
        $total = (int) DB::table('failed_jobs')->count();
        $lastDay = (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

        return [
            'key' => 'failed_jobs',
            'label' => 'Mislukte jobs',
            'status' => $lastDay > 0 ? 'warn' : 'ok',
            'value' => $total.' in totaal, '.$lastDay.' in de laatste 24 uur',
            'hint' => $lastDay > 0 ? 'Bekijk ze op de wachtrijpagina; opnieuw proberen of opruimen.' : null,
        ];
    }

    private function ai(): array
    {
        if (! config('aucvy.ai.enabled')) {
            return ['key' => 'ai', 'label' => 'Ivy', 'status' => 'warn', 'value' => 'staat uit (noodrem)', 'hint' => 'AUCVY_AI_ENABLED=false: de wizard werkt, maar Ivy doet niets.'];
        }

        $spent = (int) AiRun::query()->where('created_at', '>=', now()->startOfDay())->sum('cost_cents');
        $budget = max(1, (int) config('aucvy.ai.daily_budget_cents_total'));
        $share = $spent / $budget;
        $status = $share >= 1 ? 'fail' : ($share >= 0.8 ? 'warn' : 'ok');

        return [
            'key' => 'ai',
            'label' => 'Ivy-budget vandaag',
            'status' => $status,
            'value' => sprintf('€ %s van € %s (%d%%), provider %s', number_format($spent / 100, 2, ',', '.'), number_format($budget / 100, 2, ',', '.'), (int) round($share * 100), (string) config('aucvy.ai.provider')),
            'hint' => $status === 'ok' ? null : 'Boven het dagbudget weigert de BudgetGuard nieuwe taken tot middernacht.',
        ];
    }

    private function disk(): array
    {
        $free = (float) (@disk_free_space(base_path()) ?: 0);
        $total = (float) (@disk_total_space(base_path()) ?: 1);
        $share = $free / $total;
        $status = $share >= 0.15 ? 'ok' : ($share >= 0.05 ? 'warn' : 'fail');

        return [
            'key' => 'disk',
            'label' => 'Schijfruimte',
            'status' => $status,
            'value' => sprintf('%s vrij van %s (%d%%)', $this->bytes($free), $this->bytes($total), (int) round($share * 100)),
            'hint' => $status === 'ok' ? null : 'Uploads en logs vullen de schijf; ruim op of vergroot de ruimte.',
        ];
    }

    private function settings(): array
    {
        $production = app()->isProduction();
        $problems = [];

        if ($production && config('app.debug')) {
            $problems[] = 'APP_DEBUG staat aan';
        }

        if ($production && config('mail.default') === 'log') {
            $problems[] = 'mail gaat naar het log';
        }

        if ($production && config('aucvy.ai.provider') === 'fake') {
            $problems[] = 'Ivy draait op de nepprovider';
        }

        if (app()->isDownForMaintenance()) {
            $problems[] = 'onderhoudsmodus staat aan';
        }

        return [
            'key' => 'settings',
            'label' => 'Instellingen',
            'status' => $problems === [] ? 'ok' : ($production ? 'fail' : 'warn'),
            'value' => ($production ? 'productie' : (string) app()->environment()).', PHP '.PHP_VERSION.', Laravel '.app()->version().($problems !== [] ? ' · '.implode(', ', $problems) : ''),
            'hint' => $problems === [] ? null : 'Zet dit recht in .env en draai php artisan config:cache.',
        ];
    }

    private function errors(): array
    {
        $errors = $this->recentErrors(50);
        $lastHour = array_filter($errors, fn (array $error): bool => CarbonImmutable::parse($error['at'])->greaterThan(now()->subHour()));

        return [
            'key' => 'errors',
            'label' => 'Fouten in het log',
            'status' => $lastHour === [] ? 'ok' : 'warn',
            'value' => count($lastHour).' in het laatste uur, '.count($errors).' in de staart van het log',
            'hint' => $lastHour === [] ? null : 'De laatste regels staan hieronder; de volledige trace staat in het logbestand.',
        ];
    }

    private function seen(string $key): ?CarbonImmutable
    {
        $value = Cache::get($key);

        return $value === null ? null : CarbonImmutable::parse((string) $value);
    }

    private function newestLog(): ?string
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files[0] ?? null;
    }

    private function bytes(float $bytes): string
    {
        foreach (['B', 'kB', 'MB', 'GB', 'TB'] as $unit) {
            if ($bytes < 1024 || $unit === 'TB') {
                return number_format($bytes, $unit === 'B' ? 0 : 1, ',', '.').' '.$unit;
            }

            $bytes /= 1024;
        }

        return (string) $bytes;
    }
}
