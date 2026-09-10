<?php

declare(strict_types=1);

use App\Enums\CompanyPlan;
use App\Enums\KycStatus;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Enums\SaleType;
use App\Enums\ValueConfidence;
use App\Enums\VatRegime;

describe('labels', function () {
    it('vertaalt elke enumwaarde naar het nederlands', function () {
        // Code en enumwaarden blijven Engels; alles wat de gebruiker leest is
        // Nederlands (hfst. 4). Een ontbrekende vertaling geeft de sleutel
        // terug - daar valt deze test op.
        $enums = [
            LotStatus::class, SaleType::class, VatRegime::class,
            ValueConfidence::class, OrderStatus::class, KycStatus::class,
            CompanyPlan::class,
        ];

        foreach ($enums as $enum) {
            foreach ($enum::cases() as $case) {
                expect($case->label())
                    ->not->toContain('enums.')
                    ->not->toBe('');
            }
        }
    });

    it('noemt de verkoopvormen zoals ze op het scherm staan', function () {
        expect(SaleType::Auction->label())->toBe('Veiling')
            ->and(SaleType::Dutch->label())->toBe('Afslag')
            ->and(SaleType::BuyNow->label())->toBe('Koop direct');
    });
});

describe('kavelstatus', function () {
    it('houdt concepten en beoordelingen buiten de publieke weergave', function () {
        expect(LotStatus::Draft->isPublic())->toBeFalse()
            ->and(LotStatus::PendingReview->isPublic())->toBeFalse()
            ->and(LotStatus::Live->isPublic())->toBeTrue();
    });

    it('accepteert biedingen in de slotfase', function () {
        expect(LotStatus::Live->acceptsBids())->toBeTrue()
            ->and(LotStatus::Closing->acceptsBids())->toBeTrue()
            ->and(LotStatus::Sold->acceptsBids())->toBeFalse()
            ->and(LotStatus::Draft->acceptsBids())->toBeFalse();
    });
});

describe('orderstatusmachine', function () {
    it('loopt van hamerslag naar afgerond', function () {
        expect(OrderStatus::AwaitingPayment->canTransitionTo(OrderStatus::Paid))->toBeTrue()
            ->and(OrderStatus::Paid->canTransitionTo(OrderStatus::Shipped))->toBeTrue()
            ->and(OrderStatus::Shipped->canTransitionTo(OrderStatus::Delivered))->toBeTrue()
            ->and(OrderStatus::Delivered->canTransitionTo(OrderStatus::Completed))->toBeTrue();
    });

    it('verbiedt het overslaan van de betaling', function () {
        expect(OrderStatus::AwaitingPayment->canTransitionTo(OrderStatus::Delivered))->toBeFalse()
            ->and(OrderStatus::AwaitingPayment->canTransitionTo(OrderStatus::Completed))->toBeFalse();
    });

    it('laat een afgeronde order niet meer terugdraaien', function () {
        expect(OrderStatus::Completed->allowedTransitions())->toBe([])
            ->and(OrderStatus::Cancelled->allowedTransitions())->toBe([]);
    });

    it('kan vanaf levering nog naar een geschil', function () {
        expect(OrderStatus::Delivered->canTransitionTo(OrderStatus::Disputed))->toBeTrue();
    });

    it('geeft alleen recht op een review na afronding', function () {
        expect(OrderStatus::Completed->allowsReview())->toBeTrue()
            ->and(OrderStatus::Delivered->allowsReview())->toBeFalse()
            ->and(OrderStatus::Disputed->allowsReview())->toBeFalse();
    });
});

describe('zekerheid', function () {
    it('staat geen exact bedrag toe bij lage zekerheid', function () {
        // Een productregel, geen stijlkeuze (hfst. 8).
        expect(ValueConfidence::Low->allowsExactAdvice())->toBeFalse()
            ->and(ValueConfidence::Medium->allowsExactAdvice())->toBeTrue()
            ->and(ValueConfidence::High->allowsExactAdvice())->toBeTrue();
    });
});

describe('verificatie', function () {
    it('laat alleen een geverifieerde gebruiker bieden', function () {
        expect(KycStatus::Verified->allowsBidding())->toBeTrue()
            ->and(KycStatus::Pending->allowsBidding())->toBeFalse()
            ->and(KycStatus::Unverified->allowsBidding())->toBeFalse()
            ->and(KycStatus::Rejected->allowsBidding())->toBeFalse();
    });
});

describe('tarieven', function () {
    it('hanteert de tarieven uit de designs', function () {
        expect(CompanyPlan::Start->defaultFeePercentage())->toBe(7.5)
            ->and(CompanyPlan::Pro->defaultFeePercentage())->toBe(3.5)
            ->and(CompanyPlan::Enterprise->defaultFeePercentage())->toBe(2.5);
    });
});

describe('btw', function () {
    it('kent geen verrekenbare btw bij de margeregeling', function () {
        expect(VatRegime::Margin->isDeductible())->toBeFalse()
            ->and(VatRegime::Vat21->isDeductible())->toBeTrue();
    });
});
