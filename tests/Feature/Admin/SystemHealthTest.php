<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\SystemAlert;
use App\Support\Health\Health;
use App\Support\Health\HeartbeatJob;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/*
| Systeemgezondheid zonder Horizon of Sentry (briefing sectie 8): hartslag
| van scheduler en workers, wachtrij, Ivy-budget, schijf, instellingen en
| de laatste fouten uit het log; met een cron-commando dat mailt.
*/

beforeEach(function () {
    Cache::flush();
    config(['aucvy.support.email' => 'support@aucvy.test']);
});

function fakeLog(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'aucvy-log');
    file_put_contents($path, $contents);

    return $path;
}

it('ziet een stilstaande scheduler en worker, en een levende na de hartslag', function () {
    $health = new Health(fakeLog(''));
    $checks = collect($health->checks())->keyBy('key');

    expect($checks['scheduler']['status'])->toBe('fail')
        ->and($checks['worker.default']['status'])->toBe('fail')
        ->and(Health::overall($health->checks()))->toBe('fail');

    Cache::forever(Health::SCHEDULER_KEY, now()->toIso8601String());
    (new HeartbeatJob('default'))->handle();
    (new HeartbeatJob('realtime'))->handle();
    (new HeartbeatJob('ai'))->handle();

    $checks = collect($health->checks())->keyBy('key');

    expect($checks['scheduler']['status'])->toBe('ok')
        ->and($checks['worker.default']['status'])->toBe('ok')
        ->and($checks['worker.ai']['value'])->toContain('laatst gezien');

    $this->travelTo(now()->addMinutes(12));
    $checks = collect($health->checks())->keyBy('key');
    expect($checks['scheduler']['status'])->toBe('fail')->and($checks['worker.default']['status'])->toBe('warn');
});

it('leest de laatste fouten uit het log en telt die van het laatste uur', function () {
    $now = now()->format('Y-m-d H:i:s');
    $old = now()->subHours(3)->format('Y-m-d H:i:s');
    $log = "[{$old}] production.ERROR: Oude fout {\"exception\":\"x\"}\n#0 trace\n[{$now}] production.INFO: Niets aan de hand\n[{$now}] production.ERROR: Verbinding met Reverb geweigerd {\"exception\":\"y\"}\n#0 trace\n[{$now}] production.CRITICAL: Schijf vol\n";
    $health = new Health(fakeLog($log));

    $errors = $health->recentErrors();

    expect($errors)->toHaveCount(3)
        ->and($errors[0]['level'])->toBe('CRITICAL')
        ->and($errors[1]['message'])->toContain('Reverb geweigerd')
        ->and(collect($health->checks())->keyBy('key')['errors']['value'])->toStartWith('2 in het laatste uur');
});

it('toont de systeempagina aan beheer en laat het cron-commando mailen bij een fout, één keer per uur', function () {
    Notification::fake();
    $staff = User::factory()->create(['is_staff' => true]);

    $this->actingAs($staff)->get(route('admin.system'))
        ->assertOk()
        ->assertSee('Scheduler')
        ->assertSee('nooit gezien')
        ->assertSee('Ivy-budget vandaag')
        ->assertSee('schedule:run');

    $this->actingAs(User::factory()->create())->get(route('admin.system'))->assertForbidden();

    $this->artisan('system:health', ['--notify' => true])->assertFailed();
    Notification::assertSentOnDemand(SystemAlert::class, fn (SystemAlert $mail, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'support@aucvy.test' && collect($mail->failing)->pluck('key')->contains('scheduler'));

    // Dezelfde fout een minuut later: geen tweede mail.
    $this->travelTo(now()->addMinute());
    $this->artisan('system:health', ['--notify' => true])->assertFailed();
    Notification::assertSentTimes(SystemAlert::class, 1);

    Cache::forever(Health::SCHEDULER_KEY, now()->toIso8601String());
    foreach (Health::QUEUES as $queue) {
        (new HeartbeatJob($queue))->handle();
    }

    $this->artisan('system:health')->assertSuccessful();
});
