<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Testcase
|--------------------------------------------------------------------------
|
| Feature-tests draaien tegen MySQL, niet tegen sqlite. Dat is een bewuste
| keuze: de biedmotor leunt op `SELECT ... FOR UPDATE` en sqlite kent geen
| rij-locks. Een race-conditietest op sqlite is groen zonder iets te bewijzen.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Verwachtingen
|--------------------------------------------------------------------------
*/

/**
 * Bedragen zijn altijd centen: een int, nooit een float.
 * Zie hoofdstuk 4 van de technische briefing.
 */
expect()->extend('toBeCents', function () {
    expect($this->value)->toBeInt();

    return $this;
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Zet euro's om naar centen voor leesbare testdata: euros(13_100) === 1310000.
 */
function euros(int|float $amount): int
{
    return (int) round($amount * 100);
}
