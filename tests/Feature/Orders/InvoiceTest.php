<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\VatRegime;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Order;
use App\Models\User;
use App\Orders\Actions\ReportProblem;
use App\Orders\Actions\ResolveDispute;
use App\Orders\Actions\TransitionOrder;
use App\Orders\Invoicing\Invoices;
use App\Support\Money\Money;
use App\Support\PlatformFacts;

/*
| Facturen (hoofdbriefing p. 7, voorstel voor de boekhouder): bij betaling
| drie facturen met opeenvolgende nummers, alle verplichte gegevens, de
| margeregeling zonder btw met de vaste vermelding, en een creditnota bij
| een prijscorrectie. Alleen de partijen en beheer kunnen ze zien.
*/

function paidOrderWithInvoices(VatRegime $regime = VatRegime::Margin): Order
{
    $order = Order::factory()->create(['vat_regime' => $regime]);
    Location::factory()->create(['company_id' => $order->seller->company_id, 'user_id' => $order->seller_id, 'street' => 'Industrieweg', 'house_number' => '12', 'postal_code' => '3542 AD', 'city' => 'Utrecht']);
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);

    return $order->fresh(['invoices', 'buyer', 'seller.company', 'lot', 'payout']);
}

it('maakt bij betaling de kavelfactuur, de administratiekosten en de succesfee, één keer en opeenvolgend', function () {
    $order = paidOrderWithInvoices();

    expect($order->invoices)->toHaveCount(3)
        ->and($order->invoices->pluck('kind')->all())->toBe(['lot', 'admin_fee', 'fee']);

    $lot = $order->invoices->firstWhere('kind', 'lot');
    $adminFee = $order->invoices->firstWhere('kind', 'admin_fee');
    $fee = $order->invoices->firstWhere('kind', 'fee');

    expect($lot->number)->toMatch('/^V\d+-\d{4}-0001$/')
        ->and($adminFee->number)->toMatch('/^F-\d{4}-00001$/')
        ->and($fee->number)->toMatch('/^F-\d{4}-00002$/')
        ->and($lot->vat_cents)->toBe(0)
        ->and($lot->note)->toContain('Bijzondere regeling - gebruikte goederen')
        ->and($lot->parties['issuer']['name'])->toBe($order->seller->company->name)
        ->and($lot->parties['issuer']['kvk'])->toBe($order->seller->company->kvk_number)
        ->and($lot->parties['issuer']['address'][0])->toBe('Industrieweg 12')
        ->and($lot->parties['recipient']['name'])->toBe($order->buyer->name)
        ->and($lot->total_cents)->toBe($order->hammer_cents + $order->transport_cents)
        ->and($adminFee->subtotal_cents)->toBe(PlatformFacts::adminFeeCents())
        ->and($adminFee->vat_cents)->toBe(Money::percentageOf(PlatformFacts::adminFeeCents(), Money::vatRatePercent()))
        ->and($fee->subtotal_cents)->toBe($order->payout->fee_cents)
        ->and($fee->note)->toContain('Verrekend met de uitbetaling');

    // Nog eens betaald melden maakt geen tweede set.
    app(Invoices::class)->forPaidOrder($order);
    expect(Invoice::query()->where('order_id', $order->id)->count())->toBe(3);

    // Een tweede order van dezelfde verkoper krijgt het volgende nummer.
    $second = Order::factory()->create(['seller_id' => $order->seller_id, 'vat_regime' => VatRegime::Vat21]);
    app(TransitionOrder::class)->handle($second, OrderStatus::Paid);
    $secondLot = Invoice::query()->where('order_id', $second->id)->where('kind', 'lot')->firstOrFail();
    expect($secondLot->number)->toBe($lot->series.'-0002')
        ->and($secondLot->vat_cents)->toBe(Money::percentageOf($second->hammer_cents + $second->transport_cents, Money::vatRatePercent()));
});

it('toont de factuur alleen aan beide partijen en beheer, met alle verplichte gegevens', function () {
    $order = paidOrderWithInvoices();
    $lot = $order->invoices->firstWhere('kind', 'lot');
    $fee = $order->invoices->firstWhere('kind', 'fee');

    $this->actingAs($order->buyer)->get(route('invoices.show', $lot))->assertOk()
        ->assertSee($lot->number)->assertSee('KVK '.$order->seller->company->kvk_number)->assertSee('Btw-nummer')->assertSee('factuurdatum')->assertSee('leverdatum')->assertSee('Bijzondere regeling');
    $this->actingAs($order->seller)->get(route('invoices.show', $fee))->assertOk()->assertSee('Succesfee');
    $this->actingAs(User::factory()->create(['is_staff' => true]))->get(route('invoices.show', $lot))->assertOk();
    $this->actingAs(User::factory()->verified()->create())->get(route('invoices.show', $lot))->assertNotFound();

    $this->actingAs($order->buyer)->get(route('orders.show', $order))->assertOk()->assertSee('Facturen')->assertSee($lot->number)->assertDontSee($fee->number);
    $this->actingAs($order->seller)->get(route('orders.show', $order))->assertOk()->assertSee($fee->number);
    $this->actingAs($order->seller)->get(route('dashboard.finance'))->assertOk()->assertSee($lot->number);
});

it('maakt een creditnota op de kavelfactuur bij een prijscorrectie', function () {
    $order = paidOrderWithInvoices(VatRegime::Vat21);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Delivered);

    $dispute = app(ReportProblem::class)->handle($order->fresh(), $order->buyer, 'incomplete', 'Mist twee onderdelen.');
    $staff = User::factory()->create(['is_staff' => true]);
    app(ResolveDispute::class)->handle($dispute->fresh(), $staff, true, 'prijscorrectie', 'Twee onderdelen ontbreken, correctie afgesproken.', 25_000);

    $credit = Invoice::query()->where('order_id', $order->id)->where('kind', 'credit')->firstOrFail();
    $lot = Invoice::query()->where('order_id', $order->id)->where('kind', 'lot')->firstOrFail();

    expect($credit->credits_invoice_id)->toBe($lot->id)
        ->and($credit->subtotal_cents)->toBe(-25_000)
        ->and($credit->vat_cents)->toBe(-Money::percentageOf(25_000, Money::vatRatePercent()))
        ->and($credit->number)->toBe($lot->series.'-0002');

    $this->actingAs($order->buyer)->get(route('invoices.show', $credit))->assertOk()->assertSee('Creditnota')->assertSee('Op factuur '.$lot->number);
});
