<?php

declare(strict_types=1);

use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use App\Models\WatchlistItem;
use App\Notifications\DataExportReady;
use App\Support\Privacy\AnonymiseUser;
use App\Support\Privacy\ExportUserData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/*
| De AVG-huishouding van hfst. 15: bewaartermijnen als verwijderjob, inzage
| als export, verwijdering als anonimisering.
*/

it('ruimt verlopen ai-runs op maar laat verse staan', function () {
    DB::table('ai_runs')->insert([
        ['agent' => 'ivy', 'task' => 'estimate_value', 'model' => 'fake', 'input' => '[]', 'status' => 'completed', 'created_at' => now()->subMonths(13), 'updated_at' => now()->subMonths(13)],
        ['agent' => 'ivy', 'task' => 'estimate_value', 'model' => 'fake', 'input' => '[]', 'status' => 'completed', 'created_at' => now()->subMonths(2), 'updated_at' => now()->subMonths(2)],
    ]);

    $this->artisan('aucvy:prune-expired')->assertSuccessful();

    expect(DB::table('ai_runs')->count())->toBe(1);
});

it('anonimiseert een gebruiker maar laat de transactierijen staan', function () {
    $buyer = User::factory()->verified()->create(['phone' => '0612345678']);
    $lot = Lot::factory()->live()->create();
    $order = Order::factory()->completed()->create(['buyer_id' => $buyer->id, 'lot_id' => $lot->id]);
    WatchlistItem::create(['user_id' => $buyer->id, 'lot_id' => $lot->id]);

    app(AnonymiseUser::class)->handle($buyer);

    $raw = DB::table('users')->where('id', $buyer->id)->first();
    expect($raw->name)->toBe('Verwijderde gebruiker')
        ->and($raw->email)->toContain('anoniem.aucvy.test')
        ->and($raw->phone)->toBeNull()
        ->and($raw->deleted_at)->not->toBeNull();

    expect(WatchlistItem::query()->where('user_id', $buyer->id)->count())->toBe(0)
        ->and(Order::query()->whereKey($order->id)->exists())->toBeTrue();
});

it('weigert anonimisering bij een lopende bestelling', function () {
    $buyer = User::factory()->verified()->create();
    $lot = Lot::factory()->live()->create();
    Order::factory()->create(['buyer_id' => $buyer->id, 'lot_id' => $lot->id]);

    app(AnonymiseUser::class)->handle($buyer);
})->throws(RuntimeException::class);

it('schrijft de export weg en mailt de downloadlink', function () {
    Storage::fake('local');
    Notification::fake();

    $user = User::factory()->verified()->create();

    (new ExportUserData($user))->handle();

    Storage::disk('local')->assertExists("exports/user-{$user->id}.json");
    expect(Storage::disk('local')->get("exports/user-{$user->id}.json"))->toContain($user->email);
    Notification::assertSentTo($user, DataExportReady::class);
});

it('geeft de export alleen aan de eigenaar met een geldige handtekening', function () {
    Storage::fake('local');

    $user = User::factory()->verified()->create();
    $other = User::factory()->verified()->create();
    (new ExportUserData($user))->handle();

    $url = URL::temporarySignedRoute('account.export.download', now()->addHour(), ['user' => $user]);

    $this->actingAs($user)->get($url)->assertOk()->assertDownload('aucvy-gegevens.json');
    $this->actingAs($other)->get($url)->assertForbidden();
    $this->actingAs($user)->get(route('account.export.download', ['user' => $user]))->assertForbidden();
});
