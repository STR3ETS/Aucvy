<?php

declare(strict_types=1);

use App\Dashboard\ActivityFeed;
use App\Enums\DisputeStatus;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Models\AiInsight;
use App\Models\AuditLog;
use App\Models\Lot;
use App\Models\LotMedia;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\MoneyNeedsAttention;
use App\Orders\Actions\ReportProblem;
use App\Orders\Actions\TransitionOrder;
use App\Support\Privacy\AnonymiseUser;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| Review van de backoffice en het dashboard (blok S): de verkoper kan op een
| geschil reageren, een anonieme gebruiker breekt geen beheerpagina, een
| prijscorrectie is een bedrag, en zoeken plus filteren werkt samen.
*/

function regressionStaff(): User
{
    return User::factory()->create(['is_staff' => true]);
}

function regressionDisputedOrder(): array
{
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Shipped);
    app(TransitionOrder::class)->handle($order->fresh(), OrderStatus::Delivered);

    $dispute = app(ReportProblem::class)->handle($order->fresh(), $order->buyer, 'incomplete', 'Mist twee onderdelen.');

    return [$order->fresh(), $dispute];
}

describe('de reactie van de verkoper op een geschil', function () {
    it('kan via de bestelpagina en legt het geschil bij een medewerker', function () {
        [$order, $dispute] = regressionDisputedOrder();

        $this->actingAs($order->seller)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Reactie versturen');

        $this->actingAs($order->seller)
            ->post(route('orders.dispute.respond', $order), ['response' => 'De onderdelen worden nagestuurd.'])
            ->assertRedirect(route('orders.show', $order));

        expect($dispute->fresh()->status)->toBe(DisputeStatus::UnderReview)
            ->and($dispute->fresh()->seller_response)->toBe('De onderdelen worden nagestuurd.');

        // Daarna is het formulier weg en staat de reactie er voor beide partijen.
        $this->actingAs($order->buyer)->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('De onderdelen worden nagestuurd.')
            ->assertDontSee('Reactie versturen');
    });

    it('is niet voor de koper en niet voor een derde', function () {
        [$order] = regressionDisputedOrder();

        $this->actingAs($order->buyer)
            ->from(route('orders.show', $order))
            ->post(route('orders.dispute.respond', $order), ['response' => 'Ik ben de koper.'])
            ->assertSessionHasErrors('dispute');

        $this->actingAs(User::factory()->create())
            ->post(route('orders.dispute.respond', $order), ['response' => 'Ik ben niemand.'])
            ->assertNotFound();
    });
});

describe('een geanonimiseerde gebruiker', function () {
    it('breekt de beheerpagina\'s en de review van de tegenpartij niet', function () {
        $order = Order::factory()->completed()->create();
        $buyer = $order->buyer;
        $seller = $order->seller;

        app(AnonymiseUser::class)->handle($buyer);
        app(AnonymiseUser::class)->handle($seller);

        $staff = regressionStaff();
        $this->actingAs($staff)->get('/admin')->assertOk();
        $this->actingAs($staff)->get('/admin/orders')->assertOk()->assertSee('Verwijderde gebruiker');
        $this->actingAs($staff)->get(route('admin.orders.show', $order))->assertOk();

        expect($order->fresh()->buyer)->not->toBeNull()
            ->and($order->fresh()->seller->name)->toBe('Verwijderde gebruiker');
    });
});

describe('de beoordeling van een geschil', function () {
    it('eist een bedrag bij een prijscorrectie en verlaagt de uitbetaling', function () {
        Notification::fake();
        [$order, $dispute] = regressionDisputedOrder();
        $payoutBefore = (int) $order->payout->amount_cents;

        $this->actingAs(regressionStaff())
            ->post("/beheer/geschillen/{$dispute->id}", ['resolution' => 'prijscorrectie', 'notes' => 'Deel ontbreekt.'])
            ->assertSessionHasErrors('correction');

        $this->actingAs(regressionStaff())
            ->post("/beheer/geschillen/{$dispute->id}", ['resolution' => 'prijscorrectie', 'notes' => 'Deel ontbreekt.', 'correction' => '125,50'])
            ->assertSessionHasErrors('correction');

        $this->actingAs(regressionStaff())
            ->post("/beheer/geschillen/{$dispute->id}", ['resolution' => 'prijscorrectie', 'notes' => 'Deel ontbreekt.', 'correction' => '125.50'])
            ->assertRedirect(route('staff.disputes.index'));

        expect($dispute->fresh()->correction_cents)->toBe(12_550)
            ->and((int) $order->fresh()->payout->amount_cents)->toBe($payoutBefore - 12_550)
            ->and(AuditLog::query()->where('action', 'payout.corrected')->exists())->toBeTrue();

        Notification::assertSentOnDemand(MoneyNeedsAttention::class, fn (MoneyNeedsAttention $mail) => str_contains($mail->subject, 'prijscorrectie'));
    });

    it('meldt support een terugbetaling bij terugdraaien van een betaalde order', function () {
        Notification::fake();
        [$order, $dispute] = regressionDisputedOrder();

        $this->actingAs(regressionStaff())
            ->post("/beheer/geschillen/{$dispute->id}", ['resolution' => 'terugdraaien', 'notes' => 'Kapot geleverd.'])
            ->assertRedirect(route('staff.disputes.index'));

        expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
        Notification::assertSentOnDemand(MoneyNeedsAttention::class, fn (MoneyNeedsAttention $mail) => str_contains($mail->subject, 'teruggedraaid'));
    });
});

describe('de beheerlijsten', function () {
    it('combineren zoekterm en statusfilter', function () {
        $cancelled = Order::factory()->create(['status' => OrderStatus::Cancelled, 'order_number' => 'AUC-000111']);
        $paid = Order::factory()->create(['status' => OrderStatus::Paid, 'order_number' => 'AUC-000222']);

        $this->actingAs(regressionStaff())
            ->get('/admin/orders?q=AUC&status=cancelled')
            ->assertOk()
            ->assertSee($cancelled->order_number)
            ->assertDontSee($paid->order_number);
    });

    it('ruimen concepten op met bestanden en auditregel', function () {
        Storage::fake('public');
        $draft = Lot::factory()->create();
        Storage::disk('public')->put('lots/foto.jpg', 'x');
        LotMedia::create(['lot_id' => $draft->id, 'type' => 'image', 'path' => '/storage/lots/foto.jpg', 'variants' => ['original' => 'lots/foto.jpg'], 'position' => 0]);

        $this->actingAs(regressionStaff())
            ->post('/admin/kavels/concepten-verwijderen', ['ids' => [$draft->id]])
            ->assertRedirect();

        expect(Lot::query()->whereKey($draft->id)->exists())->toBeFalse()
            ->and(AuditLog::query()->where('action', 'lot.draft_deleted')->where('subject_id', $draft->id)->exists())->toBeTrue();
        Storage::disk('public')->assertMissing('lots/foto.jpg');
    });
});

describe('supporttickets', function () {
    it('sluiten één keer en tonen de interne notitie niet aan de melder', function () {
        $user = User::factory()->verified()->create();
        $ticket = SupportTicket::factory()->create(['user_id' => $user->id]);
        $staff = regressionStaff();

        $this->actingAs($staff)->post(route('admin.support.close', $ticket), ['reply' => 'Verdacht account, niet meer beantwoorden.'])->assertRedirect();
        $closedAt = $ticket->fresh()->closed_at;

        $this->travel(1)->hour();
        $this->actingAs($staff)->post(route('admin.support.close', $ticket), ['reply' => 'Tweede keer.'])->assertRedirect();

        expect($ticket->fresh()->closed_at->equalTo($closedAt))->toBeTrue()
            ->and($ticket->fresh()->reply)->toBe('Verdacht account, niet meer beantwoorden.');

        $feed = app(ActivityFeed::class)->for($user, AiInsight::query()->where('user_id', $user->id)->current()->get());
        $body = collect($feed['items'])->firstWhere('kind', 'ticket')['body'];

        expect($body)->not->toContain('Verdacht account');
    });
});

describe('het dashboard', function () {
    it('toont mijn kavels met de omslagfoto zonder alle mediarijen te laden', function () {
        $lot = Lot::factory()->live()->create(['title' => 'Palletwagen 2500 kg']);
        LotMedia::create(['lot_id' => $lot->id, 'type' => 'image', 'path' => '/storage/lots/tweede.jpg', 'variants' => [], 'position' => 1]);
        LotMedia::create(['lot_id' => $lot->id, 'type' => 'image', 'path' => '/storage/lots/omslag.jpg', 'variants' => [], 'position' => 0]);

        $this->actingAs($lot->seller)->get('/dashboard/kavels')
            ->assertOk()
            ->assertSee('Palletwagen 2500 kg')
            ->assertSee('/storage/lots/omslag.jpg')
            ->assertDontSee('/storage/lots/tweede.jpg');
    });

    it('maakt van een kaveltitel geen formule in de csv', function () {
        $seller = User::factory()->verified()->create();
        $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Sold, 'title' => '=HYPERLINK("http://kwaad.example";"Open")']);
        Order::factory()->completed()->create(['seller_id' => $seller->id, 'lot_id' => $lot->id]);

        $csv = $this->actingAs($seller)->get(route('dashboard.finance.export'))->assertOk()->getContent();

        expect($csv)->toContain('"\'=HYPERLINK(')
            ->and($csv)->not->toContain('"=HYPERLINK(');
    });
});
