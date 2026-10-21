<?php

declare(strict_types=1);

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Order;
use App\Models\User;
use App\Orders\Actions\ReportProblem;
use App\Orders\Actions\ResolveDispute;
use App\Orders\Actions\RespondToDispute;
use App\Orders\Actions\TransitionOrder;
use Illuminate\Validation\ValidationException;

/*
| De geschilroute uit hfst. 9: melden binnen 48 uur na levering, verkoper
| reageert binnen 72 uur, een medewerker beoordeelt. De uitbetaling staat de
| hele route stil.
*/

function deliveredOrder(): Order
{
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Delivered);

    return $order->fresh();
}

it('meldt een probleem binnen de termijn en zet de order op disputed', function () {
    $order = deliveredOrder();

    $dispute = app(ReportProblem::class)->handle($order, $order->buyer, 'damaged', 'Deur zit vol deuken.');

    expect($dispute->status)->toBe(DisputeStatus::Reported)
        ->and($dispute->sla_due_at->greaterThan(now()->addHours(71)))->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Disputed)
        ->and($order->fresh()->payout->status)->toBe(PayoutStatus::Held);
});

it('weigert een melding na de meldtermijn', function () {
    $order = deliveredOrder();

    $this->travel(49)->hours();

    expect(fn () => app(ReportProblem::class)->handle($order->fresh(), $order->buyer, 'damaged'))
        ->toThrow(ValidationException::class);
});

it('mag ook al melden terwijl de kavel onderweg is', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);

    $dispute = app(ReportProblem::class)->handle($order->fresh(), $order->buyer, 'not_received', 'Nooit aangekomen.');

    expect($dispute->exists)->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Disputed);
});

it('weigert een melding van iemand anders dan de koper', function () {
    $order = deliveredOrder();

    expect(fn () => app(ReportProblem::class)->handle($order, User::factory()->create(), 'damaged'))
        ->toThrow(ValidationException::class);
});

it('laat de verkoper reageren en legt het geschil bij een medewerker', function () {
    $order = deliveredOrder();
    $dispute = app(ReportProblem::class)->handle($order, $order->buyer, 'incomplete', 'Mist twee onderdelen.');

    app(RespondToDispute::class)->handle($dispute, $order->seller, 'De onderdelen liggen nog hier, worden nagestuurd.');

    expect($dispute->fresh()->status)->toBe(DisputeStatus::UnderReview)
        ->and($dispute->fresh()->seller_responded_at)->not->toBeNull();
});

describe('de beoordeling', function () {
    it('kan afwikkelen: order afgerond, uitbetaling gaat weer lopen', function () {
        $order = deliveredOrder();
        $dispute = app(ReportProblem::class)->handle($order, $order->buyer, 'damaged', 'Kras op de zijkant.');

        $payoutBefore = (int) $order->fresh()->payout->amount_cents;

        app(ResolveDispute::class)->handle(
            $dispute,
            User::factory()->create(),
            upheld: true,
            resolution: 'prijscorrectie',
            notes: 'Verkoper en koper akkoord met € 150 correctie.',
            correctionCents: 15_000,
        );

        // Afgerond: de vrijgave-job mag hem nu meenemen zodra de
        // houdtermijn om is. De correctie gaat van de uitbetaling af en
        // staat als bedrag op het geschil, niet alleen in de motivering.
        expect($dispute->fresh()->status)->toBe(DisputeStatus::Resolved)
            ->and($dispute->fresh()->correction_cents)->toBe(15_000)
            ->and($order->fresh()->status)->toBe(OrderStatus::Completed)
            ->and((int) $order->fresh()->payout->amount_cents)->toBe($payoutBefore - 15_000);

        $this->travel(3)->days();
        $this->artisan('orders:advance');

        expect($order->fresh()->payout->status)->toBe(PayoutStatus::Released);
    });

    it('kan terugdraaien: order geannuleerd, payout vervalt', function () {
        $order = deliveredOrder();
        $dispute = app(ReportProblem::class)->handle($order, $order->buyer, 'not_working', 'Start niet.');

        app(ResolveDispute::class)->handle(
            $dispute,
            User::factory()->create(),
            upheld: true,
            resolution: 'terugdraaien',
            notes: 'Machine aantoonbaar defect geleverd; koop ontbonden.',
        );

        expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
            ->and($order->fresh()->payout->status)->toBe(PayoutStatus::Failed);
    });
});
