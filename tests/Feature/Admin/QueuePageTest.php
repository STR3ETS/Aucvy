<?php

declare(strict_types=1);

use App\Console\Commands\AlertFailedJobs;
use App\Models\User;
use App\Notifications\FailedJobsAlert;
use Carbon\CarbonInterface;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/*
| De wachtrij in de backoffice (briefing sectie 8): wat wacht, wat mislukte,
| opnieuw proberen of opruimen, en het alarm naar support.
*/

function failedJob(string $job, string $error = 'RuntimeException: Verbinding geweigerd', ?CarbonInterface $failedAt = null): string
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'realtime',
        'payload' => json_encode(['uuid' => $uuid, 'displayName' => $job, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => ['commandName' => $job, 'command' => 'O:1:"x":0:{}']]),
        'exception' => $error."\n#0 trace",
        'failed_at' => $failedAt ?? now(),
    ]);

    return $uuid;
}

it('toont de wachtrij met mislukte jobs en laat beheer opruimen en opnieuw proberen', function () {
    config(['aucvy.support.email' => 'support@aucvy.test']);
    $staff = User::factory()->create(['is_staff' => true]);
    $uuid = failedJob('App\\Auctions\\Events\\BidPlaced');
    failedJob('App\\Ivy\\Jobs\\RunAiTask', 'Anthropic\\Exception: 529 overloaded');

    DB::table('jobs')->insert(['queue' => 'ai', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

    $this->actingAs($staff)->get(route('admin.queue'))
        ->assertOk()
        ->assertSee('BidPlaced')
        ->assertSee('RunAiTask')
        ->assertSee('529 overloaded')
        ->assertSee('2 mislukt in totaal');

    $this->actingAs($staff)->post(route('admin.queue.forget'), ['uuid' => $uuid])->assertRedirect(route('admin.queue'));
    expect(DB::table('failed_jobs')->count())->toBe(1);

    $this->actingAs($staff)->post(route('admin.queue.retry'))->assertRedirect(route('admin.queue'));
    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(2)
        ->and(DB::table('audit_logs')->where('action', 'queue.retried')->exists())->toBeTrue();

    $this->actingAs(User::factory()->create())->get(route('admin.queue'))->assertForbidden();
});

it('mailt support één keer over nieuwe mislukte jobs', function () {
    Notification::fake();
    config(['aucvy.support.email' => 'support@aucvy.test']);
    Cache::forget(AlertFailedJobs::WATERMARK);

    // Zonder ijkpunt telt alleen het laatste kwartier: wat ouder is, is al bekend.
    failedJob('App\\Ivy\\Jobs\\RunAiTask', 'oud', now()->subMinutes(30));
    $this->artisan('queue:alert')->assertSuccessful();
    Notification::assertNothingSent();

    failedJob('App\\Auctions\\Events\\BidPlaced');
    failedJob('App\\Auctions\\Events\\BidPlaced');

    $this->artisan('queue:alert')->assertSuccessful();

    Notification::assertSentOnDemand(FailedJobsAlert::class, fn (FailedJobsAlert $mail, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'support@aucvy.test' && $mail->byJob[0]['total'] === 2);

    // Niets nieuws: geen tweede mail.
    $this->artisan('queue:alert')->assertSuccessful();
    Notification::assertSentTimes(FailedJobsAlert::class, 1);

    // IJkpunt kwijt (cache geleegd): een verse fout uit het laatste kwartier wordt toch gemeld.
    Cache::forget(AlertFailedJobs::WATERMARK);
    failedJob('App\\Auctions\\Events\\Outbid', 'vers', now()->subMinutes(5));
    $this->artisan('queue:alert')->assertSuccessful();
    Notification::assertSentTimes(FailedJobsAlert::class, 2);
});
