<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
| De control room blijft werken als een bieder uit de laatste biedingen
| intussen geanonimiseerd (soft-deleted) is.
*/

beforeEach(fn () => Queue::fake());

it('toont de laatste biedingen ook als de bieder verwijderd is', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    $bidder = User::factory()->verified()->create(['name' => 'Weg Gebruiker']);
    $lot = Lot::factory()->create(['status' => LotStatus::Live, 'title' => 'Heftruck met verdwenen bieder']);
    $auction = Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addHour()]);
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $bidder->id, 'amount_cents' => 50_000, 'sequence' => 1, 'placed_at' => now()]);

    $bidder->delete();

    // Geen 500: de bieding blijft staan, met de (geanonimiseerde) naam die er nog is.
    $this->actingAs($staff)->get(route('admin.live'))
        ->assertOk()
        ->assertSee('Heftruck met verdwenen bieder')
        ->assertSee('€ 500,00');

    $this->actingAs($staff)->get(route('admin.live.panel'))->assertOk();
});
