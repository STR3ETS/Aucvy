<?php

declare(strict_types=1);

use App\Support\Health\Health;
use App\Support\Health\HeartbeatJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Geplande taken
|--------------------------------------------------------------------------
*/

/*
 | Het vangnet onder CloseAuctionJob. Hoort niets te vinden; als hij
 | structureel wél iets vindt is dat een incident (hfst. 6).
 |
 | withoutOverlapping: bij een trage database zou een tweede uitvoering
 | anders dezelfde veiling nog eens proberen te sluiten.
 */
Schedule::command('auctions:close-overdue')
    ->everyMinute()
    // Vijf minuten: een gedode run (herstart, OOM) mag de volgende minuut
    // niet een dag lang blokkeren, en langer dan dit duurt hij nooit.
    ->withoutOverlapping(5)
    ->runInBackground();

/*
 | De klokken van hfst. 9: betaaltermijn, automatisch leveren, meldtermijn en
 | de vrijgave van payouts. De zekerheidspagina belooft die termijnen; deze
 | job dwingt ze af.
 */
Schedule::command('orders:advance')
    ->everyMinute()
    // Vijf minuten: een gedode run (herstart, OOM) mag de volgende minuut
    // niet een dag lang blokkeren, en langer dan dit duurt hij nooit.
    ->withoutOverlapping(5)
    ->runInBackground();

// Dead-letter-alarm (briefing sectie 8): één mail naar support als er sinds de vorige controle jobs mislukten.
Schedule::command('queue:alert')->everyFifteenMinutes();

// Onafgemaakte uploads in delen ruimen na een dag op.
Schedule::command('uploads:prune --hours=6')->hourly();

/*
 | Hartslagen voor de systeempagina (briefing sectie 8, zonder Horizon):
 | de scheduler schrijft elke minuut een tijdstip, elke queue krijgt een
 | lege job die de worker aftekent. system:health leest ze en mailt support
 | bij een fout, hooguit één keer per uur per controle.
 */
Schedule::call(fn () => Cache::forever(Health::SCHEDULER_KEY, now()->toIso8601String()))->everyMinute()->name('heartbeat:scheduler');

foreach (Health::QUEUES as $queue) {
    Schedule::job(new HeartbeatJob($queue))->everyMinute()->name('heartbeat:worker:'.$queue);
}

Schedule::command('system:health --notify')->everyFifteenMinutes();

/*
 | De wekelijkse Ivy-bevindingen voor het dashboard (hfst. 13.5). Maandag
 | vroeg: de verkoper begint zijn week met een vers overzicht.
 */
Schedule::command('ivy:weekly-insights')
    ->weeklyOn(1, '07:00')
    ->withoutOverlapping(60);

/*
 | Alerts en volglijst (briefing sectie 3): bewaarde zoekopdrachten langs de
 | nieuwe kavels, en een seintje als een gevolgde veiling bijna sluit.
 */
Schedule::command('alerts:match')
    ->hourly()
    ->withoutOverlapping(60);

Schedule::command('watchlist:notify-ending')
    ->everyFifteenMinutes()
    ->withoutOverlapping(60);

/*
 | De bewaartermijnen uit hfst. 15: in code afgedwongen, niet in een
 | document beloofd. 's Nachts, buiten de drukte.
 */
// Control center (briefing nieuwe functies): dagcijfers, saldocontrole en de
// reactietijd van tickets.
Schedule::command('aucvy:build-metrics')->dailyAt('00:20');
Schedule::command('aucvy:reconcile-balance')->dailyAt('06:30');
Schedule::command('aucvy:check-ticket-sla')->everyFifteenMinutes();
Schedule::command('aucvy:send-reports')->dailyAt('07:00');
Schedule::command('aucvy:social-push')->everyFiveMinutes();
Schedule::command('aucvy:hot-score')->hourly();

Schedule::command('aucvy:prune-expired')
    ->dailyAt('04:00')
    ->withoutOverlapping(60);
