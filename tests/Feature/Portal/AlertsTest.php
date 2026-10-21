<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Lot;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\AlertMatches;
use Illuminate\Support\Facades\Notification;

/*
| Alerts (briefing sectie 3 en 8): een zoekopdracht bewaren vanaf de
| zoekpagina, elk uur langs de nieuwe kavels, en alleen bij een treffer een
| bericht. In-app altijd, per mail als de gebruiker dat wil.
*/

function liveLotIn(Category $category, string $title, int $priceCents = 100_000): Lot
{
    $lot = Lot::factory()->create([
        'status' => LotStatus::Live,
        'category_id' => $category->id,
        'title' => $title,
        'published_at' => now(),
    ]);
    Auction::factory()->create(['lot_id' => $lot->id, 'current_price_cents' => $priceCents, 'ends_at' => now()->addDays(3)]);

    return $lot;
}

it('bewaart de huidige filters als alert vanaf de zoekpagina', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['slug' => 'heftrucks', 'name' => 'Heftrucks']);

    $this->actingAs($user)
        ->get(route('lots.index', ['q' => 'linde', 'categorie' => 'heftrucks']))
        ->assertOk()
        ->assertSee('Alert instellen');

    $this->actingAs($user)
        ->post(route('dashboard.alerts.store'), ['query' => ['q' => 'linde', 'categorie' => 'heftrucks', 'onzin' => 'x']])
        ->assertRedirect(route('dashboard.alerts'));

    $search = SavedSearch::query()->sole();

    expect($search->user_id)->toBe($user->id)
        ->and($search->query)->toBe(['q' => 'linde', 'categorie' => 'heftrucks'])
        ->and($search->last_run_at)->not->toBeNull();

    $this->actingAs($user)->get(route('dashboard.alerts'))->assertOk()->assertSee('linde');
});

it('weigert een alert zonder zoekterm of filter', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('lots.index'))
        ->post(route('dashboard.alerts.store'), ['query' => ['sorteer' => 'nieuwste']])
        ->assertSessionHasErrors('query');

    expect(SavedSearch::query()->count())->toBe(0);
});

it('stuurt een bericht voor nieuwe passende kavels, en niets zonder treffer', function () {
    Notification::fake();

    $user = User::factory()->create();
    $heftrucks = Category::factory()->create(['slug' => 'heftrucks']);
    $keukens = Category::factory()->create(['slug' => 'keukens']);

    $search = SavedSearch::query()->create([
        'user_id' => $user->id,
        'query' => ['categorie' => 'heftrucks'],
        'last_run_at' => now()->subHour(),
    ]);

    // Te oud, verkeerde categorie, en één echte treffer.
    liveLotIn($heftrucks, 'Oude heftruck')->forceFill(['published_at' => now()->subDays(2)])->save();
    liveLotIn($keukens, 'Keuken');
    $match = liveLotIn($heftrucks, 'Linde H25 nieuw');

    // De matcher laat een halve minuut speling; een minuut vooruit telt alles mee.
    $this->travelTo(now()->addMinute());
    $this->artisan('alerts:match')->assertSuccessful();

    Notification::assertSentTo($user, AlertMatches::class, function (AlertMatches $notification) use ($match, $user) {
        return $notification->lots->pluck('id')->all() === [$match->id]
            && in_array('mail', $notification->via($user), true)
            && in_array('database', $notification->via($user), true);
    });

    expect($search->fresh()->last_run_at->greaterThan(now()->subMinute()))->toBeTrue();

    // Tweede ronde zonder nieuwe kavels: stil.
    Notification::fake();
    // De matcher laat een halve minuut speling; een minuut vooruit telt alles mee.
    $this->travelTo(now()->addMinute());
    $this->artisan('alerts:match')->assertSuccessful();
    Notification::assertNothingSent();
});

it('laat een gepauzeerde alert met rust en respecteert de mailvoorkeur', function () {
    Notification::fake();

    $quiet = User::factory()->create(['notification_prefs' => ['alerts_email' => false]]);
    $paused = User::factory()->create();
    $heftrucks = Category::factory()->create(['slug' => 'heftrucks']);

    SavedSearch::query()->create(['user_id' => $quiet->id, 'query' => ['categorie' => 'heftrucks'], 'last_run_at' => now()->subHour()]);
    SavedSearch::query()->create(['user_id' => $paused->id, 'query' => ['categorie' => 'heftrucks'], 'last_run_at' => now()->subHour(), 'paused_at' => now()]);

    liveLotIn($heftrucks, 'Linde H25');

    // De matcher laat een halve minuut speling; een minuut vooruit telt alles mee.
    $this->travelTo(now()->addMinute());
    $this->artisan('alerts:match')->assertSuccessful();

    Notification::assertSentTo($quiet, AlertMatches::class, fn (AlertMatches $notification) => $notification->via($quiet) === ['database']);
    Notification::assertNotSentTo($paused, AlertMatches::class);
});

it('pauzeert, hernoemt en verwijdert alleen eigen alerts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = SavedSearch::query()->create(['user_id' => $user->id, 'query' => ['q' => 'linde']]);
    $theirs = SavedSearch::query()->create(['user_id' => $other->id, 'query' => ['q' => 'steiger']]);

    $this->actingAs($user)->patch(route('dashboard.alerts.update', $mine), ['paused' => 1])->assertRedirect();
    expect($mine->fresh()->paused_at)->not->toBeNull();

    $this->actingAs($user)->patch(route('dashboard.alerts.update', $mine), ['name' => 'Linde heftrucks'])->assertRedirect();
    expect($mine->fresh()->name)->toBe('Linde heftrucks');

    $this->actingAs($user)->patch(route('dashboard.alerts.update', $theirs), ['paused' => 1])->assertNotFound();
    $this->actingAs($user)->delete(route('dashboard.alerts.destroy', $theirs))->assertNotFound();

    $this->actingAs($user)->delete(route('dashboard.alerts.destroy', $mine))->assertRedirect();
    expect(SavedSearch::query()->whereKey($mine->id)->exists())->toBeFalse();
});
