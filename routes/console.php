<?php

declare(strict_types=1);

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
    ->withoutOverlapping()
    ->runInBackground();

/*
 | De klokken van hfst. 9: betaaltermijn, automatisch leveren, meldtermijn en
 | de vrijgave van payouts. De zekerheidspagina belooft die termijnen; deze
 | job dwingt ze af.
 */
Schedule::command('orders:advance')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

/*
 | De wekelijkse Ivy-bevindingen voor het dashboard (hfst. 13.5). Maandag
 | vroeg: de verkoper begint zijn week met een vers overzicht.
 */
Schedule::command('ivy:weekly-insights')
    ->weeklyOn(1, '07:00')
    ->withoutOverlapping();
