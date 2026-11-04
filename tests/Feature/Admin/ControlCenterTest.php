<?php

declare(strict_types=1);

use App\Admin\Ledger;
use App\Admin\StaffRole;
use App\Enums\OrderStatus;
use App\Models\AdminAccessLog;
use App\Models\CustomerTask;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\ReportReady;
use App\Notifications\TicketReply;
use App\Orders\Actions\TransitionOrder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| Het control center (briefing nieuwe functies): rollen per onderdeel, een
| grootboek dat niet dubbel boekt, tickets uit formulier en mail, de
| reactietijd die een taak wordt, een concept van Ivy dat nooit zelf
| verstuurt, inzage op de klantkaart en een rapport achter een getekende link.
*/

function staffWithRole(StaffRole $role): User
{
    $user = User::factory()->create(['is_staff' => true]);
    $user->forceFill(['staff_role' => $role->value])->save();

    return $user;
}

function paidOrderForLedger(): Order
{
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);

    return $order->fresh();
}

it('laat elke rol alleen zijn eigen onderdelen zien', function () {
    $owner = staffWithRole(StaffRole::Owner);
    $support = staffWithRole(StaffRole::Support);
    $marketing = staffWithRole(StaffRole::Marketing);

    $this->actingAs($owner)->get(route('admin.index'))->assertOk()->assertSee('Platformomzet');
    $this->actingAs($owner)->get(route('admin.finance'))->assertOk();

    $this->actingAs($support)->get(route('admin.index'))->assertOk()->assertDontSee('Platformomzet');
    $this->actingAs($support)->get(route('admin.finance'))->assertForbidden();
    $this->actingAs($support)->post(route('admin.finance.export'))->assertForbidden();
    $this->actingAs($support)->get(route('admin.service'))->assertOk();
    $this->actingAs($support)->get(route('admin.customers'))->assertOk();

    $this->actingAs($marketing)->get(route('admin.service'))->assertForbidden();
    $this->actingAs($marketing)->get(route('admin.transactions'))->assertForbidden();

    $this->actingAs(User::factory()->create())->get(route('admin.index'))->assertForbidden();
});

it('boekt een betaalde order één keer, ook als de betaling twee keer binnenkomt', function () {
    $order = paidOrderForLedger();

    expect(LedgerEntry::query()->where('order_id', $order->id)->where('type', Ledger::FEE)->count())->toBe(1)
        ->and(LedgerEntry::query()->where('order_id', $order->id)->where('type', Ledger::ADMIN_FEE)->count())->toBe(1);

    app(Ledger::class)->recordPaidOrder($order);

    expect(LedgerEntry::query()->where('order_id', $order->id)->count())->toBe(3);

    $ledger = app(Ledger::class);
    $revenueBefore = $ledger->platformRevenueCents(now()->startOfMonth(), now()->endOfMonth());

    $ledger->recordRefund($order, 10_000, 'test:1');

    expect($ledger->platformRevenueCents(now()->startOfMonth(), now()->endOfMonth()))->toBe($revenueBefore - 10_000);
});

it('toont financiën en maakt een export achter een getekende link', function () {
    Storage::fake('local');
    paidOrderForLedger();
    $finance = staffWithRole(StaffRole::Finance);

    $this->actingAs($finance)->get(route('admin.finance'))->assertOk()->assertSee('Van omzet naar winst');

    $response = $this->actingAs($finance)->post(route('admin.finance.export', ['maand' => now()->format('Y-m')]));
    $response->assertRedirect()->assertSessionHas('export_url');

    $url = session('export_url');
    expect($url)->toContain('signature=')
        ->and(AdminAccessLog::query()->where('subject_type', 'export')->exists())->toBeTrue();

    $this->actingAs($finance)->get($url)->assertOk();
    $this->actingAs($finance)->get(route('admin.reports.download', ['path' => 'exports/nep.csv']))->assertForbidden();
});

it('maakt van een contactformulier en een inkomende mail een ticket in dezelfde draad', function () {
    Notification::fake();
    config(['services.mailgun.webhook_signing_key' => 'sleutel']);

    $this->post('/contact', [
        'name' => 'Karin', 'email' => 'karin@example.test', 'subject' => 'Factuur ontbreekt bij mijn bestelling', 'message' => str_repeat('Ik mis de factuur. ', 3),
    ])->assertRedirect();

    $ticket = SupportTicket::query()->firstOrFail();
    expect($ticket->number)->toMatch('/^T-\d{4}$/')
        ->and($ticket->source)->toBe('form')
        ->and($ticket->messages()->count())->toBe(1)
        ->and($ticket->first_response_due_at)->not->toBeNull();

    $timestamp = (string) now()->getTimestamp();
    $payload = [
        'from' => 'Karin <karin@example.test>', 'subject' => 'Re: ['.$ticket->number.'] Factuur', 'stripped-text' => 'Nog steeds niets ontvangen.',
        'timestamp' => $timestamp, 'token' => 'abc', 'signature' => hash_hmac('sha256', $timestamp.'abc', 'sleutel'),
    ];
    $this->post(route('webhooks.mailgun.inbound'), $payload)->assertNoContent();
    expect($ticket->messages()->count())->toBe(2)->and(SupportTicket::query()->count())->toBe(1);

    $this->post(route('webhooks.mailgun.inbound'), [...$payload, 'signature' => 'fout'])->assertStatus(406);

    $this->post(route('webhooks.mailgun.inbound'), [...$payload, 'subject' => 'Nieuwe vraag over ophalen'])->assertNoContent();
    expect(SupportTicket::query()->count())->toBe(2)
        ->and(SupportTicket::query()->latest('id')->first()->source)->toBe('mail');
});

it('zet een ticket over de reactietijd in Wacht op jou, één keer', function () {
    $ticket = SupportTicket::factory()->create(['subject' => 'Trage vraag']);
    $ticket->forceFill(['first_response_due_at' => now()->subHour()])->save();

    $this->artisan('aucvy:check-ticket-sla')->assertSuccessful();
    $this->artisan('aucvy:check-ticket-sla')->assertSuccessful();

    expect(CustomerTask::query()->where('source_ref', "ticket-late:{$ticket->id}")->count())->toBe(1);

    $this->actingAs(staffWithRole(StaffRole::Support))->get(route('admin.service', ['filter' => 'telaat']))->assertOk()->assertSee('Trage vraag');
});

it('laat Ivy een concept maken dat nooit zelf verstuurt, en mailt pas als een medewerker verstuurt', function () {
    Notification::fake();
    $support = staffWithRole(StaffRole::Support);
    $ticket = SupportTicket::factory()->create(['subject' => 'Waar blijft mijn borg']);

    $this->actingAs($support)->post(route('admin.service.draft', $ticket))->assertRedirect();

    $draft = $ticket->messages()->where('draft', true)->first();
    expect($draft)->not->toBeNull()
        ->and($draft->sender_type)->toBe(TicketMessage::FROM_IVY);
    Notification::assertNothingSent();

    $this->actingAs($support)->get(route('admin.service.show', $ticket))->assertOk()->assertSee('Concept van Ivy');

    $this->actingAs($support)->post(route('admin.service.reply', $ticket), ['body' => 'Je borg komt binnen twee werkdagen terug.'])->assertRedirect();

    Notification::assertSentOnDemand(TicketReply::class);
    $ticket->refresh();
    expect($ticket->first_response_at)->not->toBeNull()
        ->and($ticket->messages()->where('draft', true)->count())->toBe(0)
        ->and($ticket->assigned_to)->toBe($support->id);

    $this->actingAs($support)->post(route('admin.service.resolve', $ticket))->assertRedirect();
    expect($ticket->fresh()->isOpen())->toBeFalse();
});

it('logt wie een klantkaart opent en laat notities alleen aan de juiste rollen zien', function () {
    $customer = User::factory()->verified()->create(['name' => 'Jos Klant']);
    $support = staffWithRole(StaffRole::Support);
    $marketing = staffWithRole(StaffRole::Marketing);

    $this->actingAs($support)->post(route('admin.customers.note', $customer), ['body' => 'Belt liever dan mailt.'])->assertRedirect();
    $this->actingAs($support)->get(route('admin.customers.show', $customer))->assertOk()->assertSee('Belt liever dan mailt.');

    expect(AdminAccessLog::query()->where('subject_type', 'customer')->where('subject_id', (string) $customer->id)->where('staff_id', $support->id)->exists())->toBeTrue();

    $this->actingAs($marketing)->get(route('admin.customers.show', $customer))->assertOk()->assertDontSee('Belt liever dan mailt.');
    $this->actingAs($marketing)->post(route('admin.customers.note', $customer), ['body' => 'Mag niet.'])->assertForbidden();

    $this->actingAs($support)->post(route('admin.customers.task', $customer), ['title' => 'Terugbellen over kavel'])->assertRedirect();
    $task = CustomerTask::query()->where('user_id', $customer->id)->firstOrFail();
    $this->actingAs($support)->get(route('admin.index'))->assertOk()->assertSee('Terugbellen over kavel');
    $this->actingAs($support)->post(route('admin.tasks.complete', $task))->assertRedirect();
    expect($task->fresh()->done_at)->not->toBeNull();
});

it('plant een rapport in en stuurt het als het aan de beurt is', function () {
    Notification::fake();
    Storage::fake('local');
    $owner = staffWithRole(StaffRole::Owner);

    $this->actingAs($owner)->post(route('admin.reports.schedule'), ['report' => 'revenue', 'frequency' => 'weekly', 'recipients' => 'don@example.test, raph@example.test'])->assertRedirect();
    $this->actingAs($owner)->get(route('admin.reports'))->assertOk()->assertSee('don@example.test');

    $this->travel(8)->days();
    $this->artisan('aucvy:send-reports')->assertSuccessful();

    Notification::assertSentOnDemand(ReportReady::class);
});
