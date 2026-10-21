<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use App\Orders\Actions\TransitionOrder;
use App\Risk\RiskScan;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\Queue;

/*
| Risicosignalen (briefing sectie 7): vaste regels over eigen data, geen
| score. Bieden op eigen kavel, zelfde adres als de verkoper, herhaald niet
| betalen, herhaald afgewezen.
*/

beforeEach(fn () => Queue::fake());

it('ziet een bod op een eigen kavel of een kavel van het eigen bedrijf', function () {
    $seller = User::factory()->verified()->business()->create();
    $colleague = User::factory()->verified()->create(['company_id' => $seller->company_id]);
    $stranger = User::factory()->verified()->create();

    $lot = Lot::factory()->create(['seller_id' => $seller->id, 'company_id' => $seller->company_id, 'status' => LotStatus::Live, 'title' => 'Eigen heftruck']);
    $auction = Auction::factory()->create(['lot_id' => $lot->id]);

    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $seller->id, 'amount_cents' => 10_000, 'sequence' => 1]);
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $colleague->id, 'amount_cents' => 11_000, 'sequence' => 2]);
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $stranger->id, 'amount_cents' => 12_000, 'sequence' => 3]);

    $signals = app(RiskScan::class)->signals();

    expect($signals->where('severity', 'hoog')->pluck('key')->all())->toContain('self_bid:'.$lot->id.':'.$seller->id, 'self_bid:'.$lot->id.':'.$colleague->id)
        ->and($signals->pluck('key')->all())->not->toContain('self_bid:'.$lot->id.':'.$stranger->id);
});

it('ziet een bieder vanaf hetzelfde adres als de verkoper', function () {
    $seller = User::factory()->verified()->create();
    $bidder = User::factory()->verified()->create(['name' => 'Schaduw Bieder']);
    $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Live, 'title' => 'Koelcel']);
    $auction = Auction::factory()->create(['lot_id' => $lot->id]);
    $bid = Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $bidder->id]);

    Audit::record('lot.published', $lot, $seller, ip: '83.1.2.3');
    Audit::record('bid.placed', $bid, $bidder, after: ['auction_id' => $auction->id, 'amount_cents' => 1000], ip: '83.1.2.3');

    $signal = app(RiskScan::class)->signals()->firstWhere('key', 'shared_ip:'.$lot->id.':'.$bidder->id);

    expect($signal)->not->toBeNull()
        ->and($signal['title'])->toContain('Schaduw Bieder')
        ->and($signal['body'])->toContain('83.1.2.3');
});

it('ziet wie twee keer won zonder te betalen, en wie twee keer werd afgewezen', function () {
    $buyer = User::factory()->verified()->create(['name' => 'Wan Betaler']);
    $transition = app(TransitionOrder::class);

    foreach (range(1, 2) as $i) {
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::AwaitingPayment]);
        $transition->handle($order, OrderStatus::Cancelled, context: ['reason' => 'payment_deadline_expired']);
    }

    $seller = User::factory()->verified()->create(['name' => 'Twijfel Verkoper']);
    Lot::factory()->count(2)->create(['seller_id' => $seller->id, 'status' => LotStatus::Cancelled, 'moderation_note' => 'Geen eigendomsbewijs.', 'reviewed_at' => now()]);

    $keys = app(RiskScan::class)->signals()->pluck('key')->all();

    expect($keys)->toContain('non_payer:'.$buyer->id, 'rejected_seller:'.$seller->id);
});

it('toont de signalen in de backoffice met een link om na te kijken', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    $seller = User::factory()->verified()->create(['name' => 'Opdrijver']);
    $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Live, 'title' => 'Eigen kavel']);
    $auction = Auction::factory()->create(['lot_id' => $lot->id]);
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $seller->id]);

    $this->actingAs($staff)->get('/admin/risico')
        ->assertOk()
        ->assertSee('Opdrijver')
        ->assertSee('Eigen kavel')
        ->assertSee(route('admin.users.show', $seller), escape: false);

    $this->actingAs(User::factory()->create())->get('/admin/risico')->assertForbidden();
});
