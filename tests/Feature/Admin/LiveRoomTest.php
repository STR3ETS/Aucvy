<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
| Live control room (briefing sectie 7): lopende veilingen, biedingen van
| de laatste minuten, en wat aandacht vraagt (over tijd, realtime achter).
*/

beforeEach(fn () => Queue::fake());

it('toont de lopende veilingen met stand, biedingen en sluittijd, en ververst via het paneel', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    $bidder = User::factory()->verified()->create(['name' => 'Bob Bieder']);

    $lot = Lot::factory()->create(['status' => LotStatus::Live, 'title' => 'Linde H25 heftruck']);
    $auction = Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addMinutes(20), 'current_price_cents' => 123_400, 'bid_count' => 3, 'bidder_count' => 2]);
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $bidder->id, 'amount_cents' => 123_400, 'sequence' => 3, 'placed_at' => now()->subMinute()]);

    $paused = Lot::factory()->create(['status' => LotStatus::Paused, 'title' => 'Gepauzeerde zaagtafel']);
    Auction::factory()->create(['lot_id' => $paused->id, 'ends_at' => now()->addDay(), 'paused_at' => now()]);

    $this->actingAs($staff)->get(route('admin.live'))
        ->assertOk()
        ->assertSee('Linde H25 heftruck')
        ->assertSee('€ 1.234,00')
        ->assertSee('Bob Bieder')
        ->assertSee('Gepauzeerde zaagtafel')
        ->assertSee('gepauzeerd')
        ->assertViewHas('room', fn (array $room): bool => $room['stats']['live'] === 1 && $room['stats']['paused'] === 1 && $room['stats']['closing_hour'] === 1 && $room['stats']['bids_5m'] === 1);

    $this->actingAs($staff)->get(route('admin.live.panel'))->assertOk()->assertSee('Lopende veilingen')->assertDontSee('<x-layouts', escape: false);
});

it('waarschuwt bij veilingen over tijd en houdt niet-medewerkers buiten', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    $lot = Lot::factory()->create(['status' => LotStatus::Live, 'title' => 'Te laat gesloten']);
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->subMinutes(10)]);

    $this->actingAs($staff)->get(route('admin.live'))->assertOk()->assertSee('over tijd')->assertSee('nog niet gesloten');

    $this->actingAs(User::factory()->create())->get(route('admin.live'))->assertForbidden();
});
