<?php

declare(strict_types=1);

use App\Enums\VatRegime;
use App\Support\Money\Money;

/*
| Vaste rekenvoorbeelden. De bedragen komen uit het ontwerp van de kavelpagina
| (kavel 8842: bod € 12.850, transport € 340) zodat een afwijking meteen
| herkenbaar is als "dit is niet wat op het scherm stond".
*/

const HAMER = 1_285_000;      // € 12.850,00
const TRANSPORT = 34_000;     //    € 340,00

describe('margeregeling', function () {
    it('rekent geen btw bij zelf ophalen', function () {
        $costs = Money::compute(VatRegime::Margin, HAMER);

        expect($costs->vatCents)->toBe(0)
            ->and($costs->totalCents)->toBe(1_285_000);
    });

    it('telt transport op zonder er btw over te rekenen', function () {
        // Aanname B: bijkomende kosten volgen de hoofdprestatie. Verandert dat
        // oordeel, dan faalt deze test - dat is de bedoeling.
        $costs = Money::compute(VatRegime::Margin, HAMER, TRANSPORT);

        expect($costs->vatCents)->toBe(0)
            ->and($costs->totalCents)->toBe(1_319_000)
            ->and(Money::format($costs->totalCents))->toBe('€ 13.190,00');
    });

    it('rekent geen belastbare grondslag', function () {
        $costs = Money::compute(VatRegime::Margin, HAMER, TRANSPORT);

        expect($costs->taxableBaseCents())->toBe(0);
    });
});

describe('21% btw', function () {
    it('telt de btw boven op de hamerslag', function () {
        // Aanname A: de hamerslag is exclusief btw.
        $costs = Money::compute(VatRegime::Vat21, HAMER);

        expect($costs->vatCents)->toBe(269_850)
            ->and($costs->totalCents)->toBe(1_554_850)
            ->and(Money::format($costs->totalCents))->toBe('€ 15.548,50');
    });

    it('rekent de btw over hamerslag plus transport', function () {
        $costs = Money::compute(VatRegime::Vat21, HAMER, TRANSPORT);

        expect($costs->taxableBaseCents())->toBe(1_319_000)
            ->and($costs->vatCents)->toBe(276_990)
            ->and($costs->totalCents)->toBe(1_595_990);
    });
});

describe('opgeld', function () {
    it('blijft nul in de mvp', function () {
        $costs = Money::compute(VatRegime::Margin, HAMER);

        expect(Money::buyerPremiumPercent())->toBe(0.0)
            ->and($costs->buyerPremiumCents)->toBe(0);
    });

    it('laat zich aanzetten zonder migratie', function () {
        // Het veld bestaat, de waarde is nul. Dit bewijst dat de berekening
        // klaarstaat zodra het besluit valt (hfst. 2).
        $costs = Money::compute(VatRegime::Margin, HAMER, buyerPremiumPercent: 10.0);

        expect($costs->buyerPremiumCents)->toBe(128_500)
            ->and($costs->totalCents)->toBe(1_413_500);
    });

    it('telt mee in de belastbare grondslag als er btw geldt', function () {
        $costs = Money::compute(VatRegime::Vat21, HAMER, buyerPremiumPercent: 10.0);

        expect($costs->taxableBaseCents())->toBe(1_413_500)
            ->and($costs->vatCents)->toBe(296_835);
    });
});

describe('percentages', function () {
    it('rekent de tarieven uit de designs', function () {
        expect(Money::percentageOf(HAMER, 7.5))->toBe(96_375)
            ->and(Money::percentageOf(HAMER, 3.5))->toBe(44_975)
            ->and(Money::percentageOf(HAMER, 2.5))->toBe(32_125);
    });

    it('rondt halve centen naar boven af', function () {
        // 1 cent bij 50% is precies een halve cent.
        expect(Money::percentageOf(1, 50.0))->toBe(1)
            ->and(Money::percentageOf(3, 50.0))->toBe(2);
    });

    it('rondt onder de halve cent naar beneden af', function () {
        // € 0,01 bij 21% is 0,21 cent.
        expect(Money::percentageOf(1, 21.0))->toBe(0)
            ->and(Money::percentageOf(2, 21.0))->toBe(0)
            ->and(Money::percentageOf(3, 21.0))->toBe(1);
    });

    it('geeft altijd een int terug, nooit een float', function () {
        expect(Money::percentageOf(HAMER, 7.5))->toBeCents()
            ->and(Money::percentageOf(0, 21.0))->toBeCents()
            ->and(Money::compute(VatRegime::Vat21, HAMER, TRANSPORT)->totalCents)->toBeCents();
    });

    it('blijft exact bij grote bedragen', function () {
        // € 10 miljoen. Door in basispunten te rekenen blijft dit een exacte
        // som; met een tussentijdse float zou hier een cent kunnen wegvallen.
        expect(Money::percentageOf(1_000_000_000, 21.0))->toBe(210_000_000);
    });
});

describe('weergave', function () {
    it('gebruikt nederlandse notatie', function () {
        expect(Money::format(1_285_000))->toBe('€ 12.850,00')
            ->and(Money::format(123_456_789))->toBe('€ 1.234.567,89');
    });

    it('toont centen ook als ze nul zijn', function () {
        expect(Money::format(0))->toBe('€ 0,00')
            ->and(Money::format(100))->toBe('€ 1,00');
    });

    it('vult een enkele cent aan tot twee cijfers', function () {
        expect(Money::format(5))->toBe('€ 0,05')
            ->and(Money::format(99))->toBe('€ 0,99');
    });

    it('zet het minteken vooraan', function () {
        expect(Money::format(-34_000))->toBe('-€ 340,00');
    });

    it('kan zonder symbool, voor invoervelden', function () {
        expect(Money::format(1_285_000, withSymbol: false))->toBe('12.850,00');
    });
});

describe('bewaking', function () {
    it('weigert een negatieve hamerslag', function () {
        expect(fn () => Money::compute(VatRegime::Margin, -1))
            ->toThrow(InvalidArgumentException::class);
    });

    it('weigert negatieve transportkosten', function () {
        expect(fn () => Money::compute(VatRegime::Margin, HAMER, -1))
            ->toThrow(InvalidArgumentException::class);
    });
});
