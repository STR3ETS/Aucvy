<?php

declare(strict_types=1);

use App\Support\Charts\SmoothLine;

/*
| De vloeiende lijn van de grafieken: door elk meetpunt, zonder door te
| schieten onder het laagste of boven het hoogste punt.
*/

/**
 * Alle coördinaten uit een pad, als paren van x en y.
 *
 * @return list<array{0: float, 1: float}>
 */
function smoothLineCoordinates(string $path): array
{
    preg_match_all('/(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $path, $matches, PREG_SET_ORDER);

    return array_map(fn (array $match): array => [(float) $match[1], (float) $match[2]], $matches);
}

it('begint bij het eerste punt en gaat door elk volgend punt', function () {
    $path = SmoothLine::through([[0, 80], [50, 20], [100, 60]]);

    expect($path)->toStartWith('M0,80 C')
        ->and($path)->toContain(' 50,20 C')
        ->and($path)->toEndWith(' 100,60');
});

it('schiet nergens door: de kromme blijft tussen het laagste en het hoogste punt', function () {
    // Een steile piek naast een vlak stuk, zoals een goede maand na rustige maanden.
    $points = [[0, 100], [20, 100], [40, 95], [60, 5], [80, 0], [100, 100]];

    $coordinates = smoothLineCoordinates(SmoothLine::through($points));

    expect(min(array_column($coordinates, 1)))->toBeGreaterThanOrEqual(0.0)
        ->and(max(array_column($coordinates, 1)))->toBeLessThanOrEqual(100.0)
        ->and(min(array_column($coordinates, 0)))->toBeGreaterThanOrEqual(0.0)
        ->and(max(array_column($coordinates, 0)))->toBeLessThanOrEqual(100.0);
});

it('houdt een vlak stuk vlak', function () {
    expect(SmoothLine::through([[0, 50], [50, 50], [100, 50]]))
        ->toBe('M0,50 C16.67,50 33.33,50 50,50 C66.67,50 83.33,50 100,50');
});

it('geeft bij één punt alleen het beginpunt en bij geen punten niets', function () {
    expect(SmoothLine::through([[50, 40]]))->toBe('M50,40')
        ->and(SmoothLine::through([]))->toBe('');
});
