<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;
use App\Models\WatchlistItem;
use App\Notifications\WatchlistEnding;
use Illuminate\Support\Facades\Notification;

/*
| De volglijst (briefing sectie 3): volgen en ontvolgen, en een seintje als
| een gevolgde veiling bijna sluit.
*/

function watchedLot(User $user, string $title, int $endsInMinutes = 60 * 24): Lot
{
    $lot = Lot::factory()->create(['status' => LotStatus::Live, 'title' => $title, 'published_at' => now()]);
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addMinutes($endsInMinutes)]);
    WatchlistItem::query()->create(['user_id' => $user->id, 'lot_id' => $lot->id]);

    return $lot;
}

it('toont de gevolgde kavels met stand en sluittijd, en kan ontvolgen', function () {
    $user = User::factory()->create();
    $soon = watchedLot($user, 'Rolsteiger 8 meter', 30);
    $later = watchedLot($user, 'Koelcel 20 m3');

    $this->actingAs($user)->get(route('dashboard.watchlist'))
        ->assertOk()
        ->assertSee('Rolsteiger 8 meter')
        ->assertSee('Koelcel 20 m3')
        ->assertSee('Sluit bijna');

    $this->actingAs($user)->delete(route('dashboard.watchlist.remove', $later))->assertRedirect();

    expect(WatchlistItem::query()->where('user_id', $user->id)->pluck('lot_id')->all())->toBe([$soon->id]);
});

it('zet de volglijst per stand in een eigen tabel en filtert via de tegels', function () {
    $user = User::factory()->create();
    watchedLot($user, 'Rolsteiger 8 meter', 30);
    watchedLot($user, 'Koelcel 20 m3', 60 * 72);

    // Zonder filter: wat binnen een dag sluit staat in de eerste tabel, de rest eronder.
    $this->actingAs($user)->get(route('dashboard.watchlist'))
        ->assertOk()
        ->assertSeeInOrder(['id="volglijst-bijna"', 'class="d-lottable-head"', 'Waarde volgens Atlas', 'Rolsteiger 8 meter', 'data-tip="Ontvolgen"', 'id="volglijst-loopt"', 'Koelcel 20 m3'], false);

    // De tegel filtert op wat binnen een dag sluit.
    $this->actingAs($user)->get(route('dashboard.watchlist', ['toon' => 'bijna']))
        ->assertOk()
        ->assertSee('Rolsteiger 8 meter')
        ->assertDontSee('Koelcel 20 m3');

    $this->actingAs($user)->get(route('dashboard.watchlist', ['toon' => 'gesloten']))
        ->assertOk()
        ->assertSee('Geen gesloten veilingen op je lijst.')
        ->assertDontSee('Rolsteiger 8 meter');
});

it('waarschuwt volgers één keer als de veiling binnen een uur sluit', function () {
    Notification::fake();

    $user = User::factory()->create();
    $ending = watchedLot($user, 'Sluit zo', 40);
    watchedLot($user, 'Sluit later', 180);

    $this->artisan('watchlist:notify-ending')->assertSuccessful();

    Notification::assertSentTo($user, WatchlistEnding::class, fn (WatchlistEnding $notification) => $notification->lot->is($ending));
    Notification::assertSentTimes(WatchlistEnding::class, 1);

    // Nog een ronde: niet nog een keer.
    Notification::fake();
    $this->artisan('watchlist:notify-ending')->assertSuccessful();
    Notification::assertNothingSent();
});

it('kiest mail als er geen push is en zwijgt per mail als dat zo is ingesteld', function () {
    $withMail = User::factory()->create();
    $quiet = User::factory()->create(['notification_prefs' => ['watchlist_email' => false]]);
    $lot = Lot::factory()->create(['status' => LotStatus::Live]);
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addMinutes(30)]);

    $notification = new WatchlistEnding($lot);

    expect($notification->via($withMail))->toBe(['database', 'mail'])
        ->and($notification->via($quiet))->toBe(['database']);
});
