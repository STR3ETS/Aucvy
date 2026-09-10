<?php

declare(strict_types=1);

use App\Auctions\Support\IncrementTable;

/*
| De staffel uit de briefing:
|   tot € 500     stap € 10
|   tot € 5.000   stap € 50
|   daarboven     stap € 250
*/

beforeEach(function () {
    $this->table = IncrementTable::default();
});

describe('de stap per band', function () {
    it('vraagt tien euro onder de vijfhonderd', function () {
        expect($this->table->stepFor(0))->toBe(1_000)
            ->and($this->table->stepFor(25_000))->toBe(1_000)
            // De grens hoort er nog bij.
            ->and($this->table->stepFor(50_000))->toBe(1_000);
    });

    it('vraagt vijftig euro tot vijfduizend', function () {
        expect($this->table->stepFor(50_001))->toBe(5_000)
            ->and($this->table->stepFor(500_000))->toBe(5_000);
    });

    it('vraagt tweehonderdvijftig euro daarboven', function () {
        expect($this->table->stepFor(500_001))->toBe(25_000)
            ->and($this->table->stepFor(100_000_000))->toBe(25_000);
    });
});

describe('het minimum', function () {
    it('is de startprijs zolang er niet geboden is', function () {
        // Je hoeft niet bóven de startprijs te beginnen.
        expect($this->table->minimumBid(currentPriceCents: 100_000, hasBids: false, startPriceCents: 100_000))
            ->toBe(100_000);
    });

    it('is een stap boven de stand zodra er geboden is', function () {
        expect($this->table->minimumBid(currentPriceCents: 100_000, hasBids: true, startPriceCents: 100_000))
            ->toBe(105_000);
    });

    it('gebruikt de stap die bij de huidige stand hoort', function () {
        // Niet de stap van de startprijs: een veiling die van € 400 naar
        // € 6.000 is gelopen hoort met € 250 verder te gaan, niet met € 10.
        expect($this->table->minimumBid(currentPriceCents: 600_000, hasBids: true, startPriceCents: 40_000))
            ->toBe(625_000);
    });
});

describe('bewaking', function () {
    it('weigert een lege staffel', function () {
        expect(fn () => IncrementTable::fromArray([]))
            ->toThrow(InvalidArgumentException::class);
    });

    it('weigert een staffel zonder open bovengrens', function () {
        // Zonder open bovengrens valt een bod boven de hoogste trede door de
        // mand, en dan is elke stap van één cent geldig.
        expect(fn () => IncrementTable::fromArray([
            ['up_to_cents' => 50_000, 'step_cents' => 1_000],
        ]))->toThrow(InvalidArgumentException::class);
    });

    it('weigert een aflopende staffel', function () {
        expect(fn () => IncrementTable::fromArray([
            ['up_to_cents' => 500_000, 'step_cents' => 1_000],
            ['up_to_cents' => 50_000, 'step_cents' => 5_000],
            ['up_to_cents' => null, 'step_cents' => 25_000],
        ]))->toThrow(InvalidArgumentException::class);
    });

    it('weigert een stap van nul', function () {
        expect(fn () => IncrementTable::fromArray([
            ['up_to_cents' => null, 'step_cents' => 0],
        ]))->toThrow(InvalidArgumentException::class);
    });
});
