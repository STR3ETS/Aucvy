<?php

declare(strict_types=1);

use App\Enums\CompanyPlan;
use App\Enums\VatRegime;
use App\Models\Company;
use App\Models\Lot;
use App\Models\Order;
use App\Support\Money\Money;
use App\Support\Money\OrderCosts;

it('leest het btw-regime van de kavel', function () {
    $margin = Lot::factory()->create(['vat_regime' => VatRegime::Margin]);
    $taxed = Lot::factory()->create(['vat_regime' => VatRegime::Vat21]);

    expect(Money::forOrder($margin, 1_285_000)->vatCents)->toBe(0)
        ->and(Money::forOrder($taxed, 1_285_000)->vatCents)->toBe(269_850);
});

it('geeft op de order hetzelfde bedrag als op het scherm', function () {
    /*
     | Dit is de eis uit hfst. 9, letterlijk: het totaal dat de koper ziet vóór
     | het bevestigen, het bedrag op de order en het bedrag bij de provider
     | komen alle drie uit dezelfde klasse. Lopen ze uiteen, dan is dat per
     | definitie een bug en geen afronding.
     */
    $lot = Lot::factory()->create(['vat_regime' => VatRegime::Vat21]);

    // 1. Wat de koper zag op de kavelpagina.
    $quoted = Money::forOrder($lot, 1_285_000, 34_000);

    // 2. Wat er op de order wordt vastgelegd.
    $order = Order::factory()->create([
        'lot_id' => $lot->id,
        'hammer_cents' => $quoted->hammerCents,
        'buyer_premium_cents' => $quoted->buyerPremiumCents,
        'transport_cents' => $quoted->transportCents,
        'vat_cents' => $quoted->vatCents,
        'total_cents' => $quoted->totalCents,
        'vat_regime' => $quoted->vatRegime,
    ]);

    // 3. Wat je terugleest van de order.
    $stored = OrderCosts::fromOrder($order->fresh());

    expect($stored->equals($quoted))->toBeTrue()
        ->and($stored->totalCents)->toBe(1_595_990);
});

it('merkt op als de order is gaan afwijken', function () {
    // De tegenproef: zonder deze gevoeligheid bewijst de test hierboven niets.
    $lot = Lot::factory()->create(['vat_regime' => VatRegime::Vat21]);
    $quoted = Money::forOrder($lot, 1_285_000, 34_000);

    $order = Order::factory()->create([
        'lot_id' => $lot->id,
        'hammer_cents' => $quoted->hammerCents,
        'buyer_premium_cents' => $quoted->buyerPremiumCents,
        'transport_cents' => $quoted->transportCents,
        // Eén cent verschil is al genoeg.
        'vat_cents' => $quoted->vatCents + 1,
        'total_cents' => $quoted->totalCents + 1,
        'vat_regime' => $quoted->vatRegime,
    ]);

    expect(OrderCosts::fromOrder($order->fresh())->equals($quoted))->toBeFalse();
});

describe('uitbetaling aan de verkoper', function () {
    it('trekt de succesfee van het plan af', function () {
        $company = Company::factory()->onPlan(CompanyPlan::Pro)->create();

        $proceeds = Money::payoutFor(1_285_000, $company);

        expect($proceeds->feePercentage)->toBe(3.5)
            ->and($proceeds->feeCents)->toBe(44_975)
            ->and($proceeds->payoutCents)->toBe(1_240_025)
            ->and(Money::format($proceeds->payoutCents))->toBe('€ 12.400,25');
    });

    it('hanteert het starttarief voor een particuliere verkoper', function () {
        $proceeds = Money::payoutFor(1_285_000);

        expect($proceeds->feePercentage)->toBe(7.5)
            ->and($proceeds->payoutCents)->toBe(1_188_625);
    });

    it('volgt een afwijkende afspraak op het bedrijf, niet het plan', function () {
        // Een eigen tarief voor een grote verkoper mag geen codewijziging vragen.
        $company = Company::factory()->onPlan(CompanyPlan::Start)->create([
            'fee_percentage' => 1.25,
        ]);

        $proceeds = Money::payoutFor(1_285_000, $company);

        expect($proceeds->feePercentage)->toBe(1.25)
            ->and($proceeds->feeCents)->toBe(16_063);
    });

    it('houdt de fee los van wat de koper betaalt', function () {
        // Twee verschillende sommen over hetzelfde bedrag. De koper betaalt
        // btw; de verkoper draagt de fee. Die mogen elkaar niet raken.
        $lot = Lot::factory()->create(['vat_regime' => VatRegime::Vat21]);

        $buyerPays = Money::forOrder($lot, 1_285_000);
        $sellerGets = Money::payoutFor(1_285_000);

        expect($buyerPays->totalCents)->toBe(1_554_850)
            ->and($sellerGets->payoutCents)->toBe(1_188_625);
    });
});

describe('kostenopbouw voor de ui', function () {
    it('vermeldt de margeregeling expliciet', function () {
        // Weglaten is geen optie: het scheelt een zakelijke koper 21% die hij
        // niet kan terugvorderen (hfst. 13.3).
        $lines = Money::compute(VatRegime::Margin, 1_285_000)->lines();

        $vatLine = collect($lines)->firstWhere('key', 'vat');

        expect($vatLine->note)->toBe('Margeregeling - btw niet verrekenbaar')
            ->and($vatLine->amountCents)->toBe(0);
    });

    it('noemt het btw-tarief bij een belaste kavel', function () {
        $lines = Money::compute(VatRegime::Vat21, 1_285_000)->lines();

        $vatLine = collect($lines)->firstWhere('key', 'vat');

        expect($vatLine->label)->toBe('Btw 21%')
            ->and($vatLine->formatted())->toBe('€ 2.698,50');
    });

    it('laat de opgeldregel weg zolang die nul is', function () {
        // "Opgeld € 0,00" roept de vraag op of er ooit wél opgeld komt.
        $keys = array_column(
            array_map(fn ($line) => $line->toArray(), Money::compute(VatRegime::Margin, 1_285_000)->lines()),
            'key',
        );

        expect($keys)->not->toContain('buyer_premium')
            ->and($keys)->toBe(['hammer', 'transport', 'vat']);
    });

    it('zegt erbij dat transport nul is omdat je zelf ophaalt', function () {
        $lines = Money::compute(VatRegime::Margin, 1_285_000, 0)->lines();

        expect(collect($lines)->firstWhere('key', 'transport')->note)->toBe('Zelf ophalen');
    });
});
