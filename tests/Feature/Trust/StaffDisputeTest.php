<?php

declare(strict_types=1);

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\PayoutStatus;
use App\Models\Order;
use App\Models\User;
use App\Orders\Actions\ReportProblem;
use App\Orders\Actions\TransitionOrder;

/*
| De beoordelaarskant: een medewerker, geen algoritme (hfst. 9). Achter de
| staff-gate, en elke uitkomst loopt door ResolveDispute - met motivering.
*/

function disputedOrder(): array
{
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Delivered);

    $dispute = app(ReportProblem::class)->handle($order->fresh(), $order->buyer, 'damaged', 'Kapot geleverd.');

    return [$order->fresh(), $dispute];
}

function staffUser(): User
{
    $staff = User::factory()->create();
    $staff->is_staff = true;
    $staff->save();

    return $staff;
}

it('houdt niet-medewerkers buiten', function () {
    $this->actingAs(User::factory()->create())
        ->get('/beheer/geschillen')
        ->assertForbidden();
});

it('toont de medewerker de open geschillen op volgorde van termijn', function () {
    [, $dispute] = disputedOrder();

    $this->actingAs(staffUser())
        ->get('/beheer/geschillen')
        ->assertOk()
        ->assertSee($dispute->order->order_number)
        ->assertSee('verkoper reageert vóór');
});

it('legt een terugdraai-uitkomst vast: koop ontbonden, payout vervalt', function () {
    [$order, $dispute] = disputedOrder();

    $this->actingAs(staffUser())
        ->post("/beheer/geschillen/{$dispute->id}", [
            'resolution' => 'terugdraaien',
            'notes' => 'Aantoonbaar defect geleverd; koop ontbonden.',
        ])
        ->assertRedirect(route('staff.disputes.index'));

    expect($dispute->fresh()->status)->toBe(DisputeStatus::Resolved)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->fresh()->payout->status)->toBe(PayoutStatus::Failed);
});

it('kan een melding afwijzen: order rondt af, review-recht blijft', function () {
    [$order, $dispute] = disputedOrder();

    $this->actingAs(staffUser())
        ->post("/beheer/geschillen/{$dispute->id}", [
            'resolution' => 'afgewezen',
            'notes' => 'Schade stond duidelijk op de foto\'s bij de kavel.',
        ]);

    expect($dispute->fresh()->status)->toBe(DisputeStatus::Rejected)
        ->and($order->fresh()->status)->toBe(OrderStatus::Completed);
});

it('eist altijd een motivering', function () {
    [, $dispute] = disputedOrder();

    $this->actingAs(staffUser())
        ->post("/beheer/geschillen/{$dispute->id}", ['resolution' => 'terugdraaien'])
        ->assertSessionHasErrors('notes');
});
