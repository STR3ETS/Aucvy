<?php

declare(strict_types=1);

use App\Dashboard\HeadlineStats;
use App\Enums\CompanyRole;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Lots\DraftAccess;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Company;
use App\Models\Lot;
use App\Models\Order;
use App\Models\SavedSearch;
use App\Models\User;
use App\Models\WatchlistItem;
use App\Notifications\AlertMatches;
use App\Notifications\WatchlistEnding;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
| Regressies uit de codereview van het portaal: alerts laten niets vallen
| boven de limiet, een gepauzeerde veiling is niet "bijna gesloten", de
| bevestiging in de wizard verdraagt all=false, en de dashboardtegels
| rekenen met het hele bedrijf.
*/

beforeEach(fn () => Queue::fake());

function alertLot(Category $category, string $title, int $minutesAgo): Lot
{
    $lot = Lot::factory()->create(['status' => LotStatus::Live, 'category_id' => $category->id, 'title' => $title, 'published_at' => now()->subMinutes($minutesAgo)]);
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDays(3)]);

    return $lot;
}

it('meldt kavels boven de limiet in de volgende ronde in plaats van ze te vergeten', function () {
    Notification::fake();
    $user = User::factory()->create();
    $category = Category::factory()->create(['slug' => 'graafmachines']);
    SavedSearch::query()->create(['user_id' => $user->id, 'query' => ['categorie' => 'graafmachines'], 'last_run_at' => now()->subHours(2)]);

    foreach (range(1, 12) as $index) {
        alertLot($category, 'Graafmachine '.$index, 60 - $index);
    }

    $this->artisan('alerts:match', ['--limit' => 10])->assertSuccessful();
    Notification::assertSentTo($user, AlertMatches::class, fn (AlertMatches $alert) => $alert->lots->count() === 10);

    Notification::fake();
    $this->artisan('alerts:match', ['--limit' => 10])->assertSuccessful();
    Notification::assertSentTo($user, AlertMatches::class, fn (AlertMatches $alert) => $alert->lots->count() === 2 && $alert->lots->pluck('title')->all() === ['Graafmachine 11', 'Graafmachine 12']);
});

it('waarschuwt niet voor een gepauzeerde veiling die volgens de klok bijna sluit', function () {
    Notification::fake();
    $user = User::factory()->create();
    $lot = Lot::factory()->create(['status' => LotStatus::Paused]);
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addMinutes(20), 'paused_at' => now()->subHour()]);
    WatchlistItem::query()->create(['user_id' => $user->id, 'lot_id' => $lot->id]);

    $this->artisan('watchlist:notify-ending')->assertSuccessful();

    Notification::assertNotSentTo($user, WatchlistEnding::class);
});

it('verdraagt een bevestiging met all=false', function () {
    $this->withCredentials();
    $this->postJson('/api/drafts')->assertCreated();
    $lot = Lot::sole();

    $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
        ->postJson("/api/drafts/{$lot->id}/confirm", ['all' => false])
        ->assertOk();
});

it('telt op het dashboard de verkopen van het hele bedrijf voor wie de financiën mag zien', function () {
    $company = Company::factory()->verified()->create();
    $owner = User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => CompanyRole::Owner, 'type' => 'business']);
    $finance = User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => CompanyRole::Finance, 'type' => 'business']);
    Order::factory()->create(['seller_id' => $owner->id, 'status' => OrderStatus::Completed, 'hammer_cents' => 100_000, 'completed_at' => now()->subDay()]);

    $tiles = app(HeadlineStats::class)->forSeller($finance, null)['tiles'];

    expect($tiles[0]['value'])->toBe('€ 1.000,00')
        ->and($tiles[3]['value'])->toBe('1');
});
