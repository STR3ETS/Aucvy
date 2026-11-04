<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceBid;
use App\Enums\OrderStatus;
use App\Lots\Dossier\Dossier;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\LotDocument;
use App\Models\MailDelivery;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderCreated;
use App\Orders\Actions\TransitionOrder;
use App\Support\Audit\Audit;
use Illuminate\Support\Str;

/*
| Het kaveldossier (briefing nieuwe functies, 3 november 2026): één tijdlijn
| per kavel, gevuld vanuit auditlog, veilingevents, orders, mail en
| documenten. Elke regel heeft een zichtbaarheid; een koper ziet nooit
| biedingen of gesprekken van anderen.
*/

function liveLotWithAuction(): Lot
{
    $lot = Lot::factory()->live()->create(['seller_id' => User::factory()->verified()->business()->create()->id]);
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDays(2)]);

    return $lot->fresh(['auction', 'seller']);
}

it('zet auditregels over de kavel op de tijdlijn met de juiste zichtbaarheid', function () {
    $lot = liveLotWithAuction();

    Audit::record('lot.published', $lot, null, after: ['sale_type' => 'auction', 'approved_by' => 'ivy']);
    Audit::record('lot.returned', $lot, null, after: ['issues' => [['field' => 'price', 'message' => 'Controleer de prijs.']], 'by' => 'ivy']);
    Audit::record('lot.escalated', $lot, null, after: ['issues' => [], 'by' => 'ivy']);

    $all = LotActivity::query()->where('lot_id', $lot->id)->get();

    expect($all)->toHaveCount(3)
        ->and($all->firstWhere('type', 'published')->visibility)->toBe(LotActivity::PUBLIC)
        ->and($all->firstWhere('type', 'published')->actor_type)->toBe('ivy')
        ->and($all->firstWhere('type', 'returned')->visibility)->toBe(LotActivity::SELLER)
        ->and($all->firstWhere('type', 'returned')->payload['issues'])->toBe(['Controleer de prijs.'])
        ->and($all->firstWhere('type', 'escalated')->visibility)->toBe(LotActivity::STAFF);

    // Publiek ziet alleen "live", de verkoper ook "teruggestuurd", beheer alles.
    expect(Dossier::timeline($lot, 'public')->pluck('type')->all())->toBe(['published'])
        ->and(Dossier::timeline($lot, 'seller', $lot->seller)->pluck('type')->sort()->values()->all())->toBe(['published', 'returned'])
        ->and(Dossier::timeline($lot, 'staff'))->toHaveCount(3);
});

it('zet het eerste bod publiek en overboden alleen bij wie overboden is', function () {
    $lot = liveLotWithAuction();
    $first = User::factory()->verified()->create();
    $second = User::factory()->verified()->create();

    app(PlaceBid::class)->handle($lot->auction, $first, 110_000, (string) Str::uuid());
    app(PlaceBid::class)->handle($lot->auction->fresh(), $second, 120_000, (string) Str::uuid());

    $rows = LotActivity::query()->where('lot_id', $lot->id)->where('source', 'auction')->get();

    expect($rows->firstWhere('type', 'first_bid')->visibility)->toBe(LotActivity::PUBLIC)
        ->and($rows->firstWhere('type', 'bid')->visibility)->toBe(LotActivity::STAFF)
        ->and($rows->firstWhere('type', 'outbid')->user_id)->toBe($first->id);

    // De overboden bieder ziet zijn eigen regel; de winnende bieder niet.
    expect(Dossier::timeline($lot, 'public', $first)->pluck('type')->all())->toContain('outbid')
        ->and(Dossier::timeline($lot, 'public', $second)->pluck('type')->all())->not->toContain('outbid')
        ->and(Dossier::timeline($lot, 'public')->pluck('type')->all())->not->toContain('bid');
});

it('legt een order en elke statuswijziging vast voor beide partijen', function () {
    $order = Order::factory()->create();
    app(TransitionOrder::class)->handle($order, OrderStatus::Paid);

    $rows = LotActivity::query()->where('order_id', $order->id)->where('source', 'order')->get();

    expect($rows->pluck('type')->all())->toContain('created', 'paid')
        ->and($rows->every(fn (LotActivity $row) => $row->visibility === LotActivity::PARTIES))->toBeTrue();

    $this->actingAs($order->buyer)->get(route('orders.show', $order))->assertOk()->assertSee('Order aangemaakt');
    $this->actingAs($order->seller)->get(route('orders.show', $order))->assertOk()->assertSee('Betaald');
});

it('koppelt een mail aan de kavel en toont hem alleen aan de ontvanger', function () {
    $order = Order::factory()->create();
    LotActivity::query()->delete();

    $order->buyer->notify(new OrderCreated($order, 'buyer'));

    $delivery = MailDelivery::query()->where('to_email', strtolower($order->buyer->email))->latest('id')->first();

    expect($delivery->lot_id)->toBe($order->lot_id)
        ->and($delivery->order_id)->toBe($order->id);

    $row = LotActivity::query()->where('source', 'mail')->where('type', 'sent')->sole();

    expect($row->visibility)->toBe(LotActivity::USER)
        ->and($row->user_id)->toBe($order->buyer_id)
        ->and(Dossier::timeline($order->lot, 'buyer', $order->buyer)->pluck('type')->all())->toContain('sent')
        ->and(Dossier::timeline($order->lot, 'seller', $order->seller)->pluck('type')->all())->not->toContain('sent');

    // De webhookstatus wordt een nieuwe regel, nooit een herschreven regel.
    $delivery->forceFill(['status' => MailDelivery::STATUS_DELIVERED, 'last_event_at' => now()])->save();

    expect(LotActivity::query()->where('source', 'mail')->pluck('type')->all())->toBe(['sent', 'delivered']);
});

it('legt documenten vast: toegevoegd voor de verkoper, gecontroleerd voor beide partijen', function () {
    $lot = liveLotWithAuction();
    $document = LotDocument::factory()->create(['lot_id' => $lot->id]);

    $document->forceFill(['ai_checked_at' => now(), 'ai_findings' => ['readable' => true, 'issues' => []]])->save();

    $rows = LotActivity::query()->where('lot_id', $lot->id)->where('source', 'document')->get();

    expect($rows->firstWhere('type', 'added')->visibility)->toBe(LotActivity::SELLER)
        ->and($rows->firstWhere('type', 'checked')->visibility)->toBe(LotActivity::PARTIES)
        ->and($rows->firstWhere('type', 'checked')->actor_type)->toBe('ivy');
});

it('toont de verkoper zijn kaveldossier met tabs en weigert een vreemde', function () {
    $lot = liveLotWithAuction();
    Audit::record('lot.published', $lot, $lot->seller, after: ['sale_type' => 'auction']);

    $this->actingAs($lot->seller)->get(route('dashboard.lots.show', $lot))
        ->assertOk()
        ->assertSee('Overzicht')
        ->assertSee('Dossier')
        ->assertSee('Kavel staat live');

    $this->actingAs($lot->seller)->get(route('dashboard.lots.show', [$lot, 'tab' => 'dossier', 'bron' => 'lot']))
        ->assertOk()
        ->assertSee('Kavel staat live');

    $this->actingAs($lot->seller)->get(route('dashboard.lots.show', [$lot, 'tab' => 'documenten']))
        ->assertOk()
        ->assertSee('Nog geen documenten');

    $this->actingAs(User::factory()->verified()->business()->create())->get(route('dashboard.lots.show', $lot))->assertNotFound();
});

it('toont beheer het volledige dossier met filter', function () {
    $lot = liveLotWithAuction();
    Audit::record('lot.escalated', $lot, null, after: ['issues' => [], 'by' => 'ivy']);
    Audit::record('lot.published', $lot, null, after: ['sale_type' => 'auction']);

    $this->actingAs(User::factory()->create(['is_staff' => true]))
        ->get(route('admin.lots.dossier', [$lot, 'bron' => 'lot']))
        ->assertOk()
        ->assertSee('Bij een medewerker gelegd')
        ->assertSee('Kavel staat live');

    $this->actingAs($lot->seller)->get(route('admin.lots.dossier', $lot))->assertForbidden();
});

it('bouwt het dossier opnieuw op zonder dubbele regels', function () {
    $order = Order::factory()->create();
    Audit::record('lot.published', $order->lot, null, after: ['sale_type' => 'auction']);

    $before = LotActivity::query()->count();

    $this->artisan('aucvy:dossier-backfill')->assertSuccessful();
    $this->artisan('aucvy:dossier-backfill')->assertSuccessful();

    expect(LotActivity::query()->count())->toBe($before);

    // Na een lege tabel komt alles terug.
    LotActivity::query()->delete();
    $this->artisan('aucvy:dossier-backfill')->assertSuccessful();

    expect(LotActivity::query()->whereIn('source', ['lot', 'order'])->pluck('type')->sort()->values()->all())->toBe(['created', 'published'])
        ->and(LotActivity::query()->where('source', 'mail')->count())->toBe(2);
});
