<?php

declare(strict_types=1);

use App\Ivy\Guards\ConfidencePolicy;

/*
| De regel die telt (hfst. 8): bij lage zekerheid geen exacte bedragen als
| advies. De drempels zijn productbeslissingen - wie ze verschuift, hoort
| deze test bewust aan te passen.
*/

it('vertaalt de ruwe score naar drie niveaus', function (?float $confidence, string $level) {
    expect((new ConfidencePolicy)->level($confidence))->toBe($level);
})->with([
    'geen score' => [null, 'low'],
    'laag' => [0.3, 'low'],
    'net medium' => [0.5, 'medium'],
    'net high' => [0.8, 'high'],
    'maximaal' => [1.0, 'high'],
]);

it('staat exacte bedragen alleen toe boven laag', function () {
    $policy = new ConfidencePolicy;

    expect($policy->allowsExactAmounts(0.3))->toBeFalse()
        ->and($policy->allowsExactAmounts(null))->toBeFalse()
        ->and($policy->allowsExactAmounts(0.6))->toBeTrue();
});
