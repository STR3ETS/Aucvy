<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Enums\MediaType;
use App\Enums\SaleType;
use App\Lots\Management\DuplicateLot;
use App\Lots\Management\ResumeLot;
use App\Lots\Management\UpdateLot;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Lot;
use App\Models\LotMedia;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
| Regressies uit de codereview van kavelbeheer: dupliceren kopieert de
| bestanden, een gepauzeerde afslag houdt zijn prijs stil, en opslaan
| zonder wijziging laat geen spoor na.
*/

beforeEach(fn () => Queue::fake());

it('geeft een kopie eigen fotobestanden, zodat verwijderen bij de kopie het origineel niet raakt', function () {
    Storage::fake('public');
    $seller = User::factory()->verified()->create();
    $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Live, 'public_id' => '77001']);
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDay()]);

    Storage::disk('public')->put('lots/77001/origineel.jpg', 'beeld');
    $media = LotMedia::create([
        'lot_id' => $lot->id,
        'type' => MediaType::Image,
        'path' => Storage::disk('public')->url('lots/77001/origineel.jpg'),
        'variants' => ['original' => 'lots/77001/origineel.jpg'],
        'position' => 0,
    ]);

    $copy = app(DuplicateLot::class)->handle($lot, $seller);
    $copied = $copy->media()->sole();

    expect($copied->variants['original'])->not->toBe('lots/77001/origineel.jpg')
        ->and(Storage::disk('public')->exists($copied->variants['original']))->toBeTrue();

    $this->actingAs($seller)->deleteJson("/api/drafts/{$copy->id}/media/{$copied->id}")->assertOk();

    expect(Storage::disk('public')->exists('lots/77001/origineel.jpg'))->toBeTrue()
        ->and(Storage::disk('public')->exists($copied->variants['original']))->toBeFalse()
        ->and($media->fresh())->not->toBeNull();
});

it('schuift bij een gepauzeerde afslag ook de prijsklok mee bij hervatten', function () {
    $seller = User::factory()->verified()->create();
    $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Paused, 'sale_type' => SaleType::Dutch]);
    $auction = Auction::factory()->create([
        'lot_id' => $lot->id,
        'starts_at' => now()->subHours(3),
        'ends_at' => now()->addDays(2),
        'paused_at' => now()->subHour(),
        'dutch_start_cents' => 100_000,
        'dutch_floor_cents' => 50_000,
        'dutch_step_cents' => 5_000,
        'dutch_interval_s' => 3600,
    ]);

    app(ResumeLot::class)->handle($lot, $seller);

    $auction->refresh();
    expect((int) $auction->starts_at->diffInHours(now(), true))->toBe(2)
        ->and($auction->paused_at)->toBeNull()
        ->and($lot->fresh()->status)->toBe(LotStatus::Live);
});

it('schrijft geen auditregel als er niets verandert', function () {
    $seller = User::factory()->verified()->create();
    $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Live, 'quantity' => 3, 'year' => 2020, 'pickup_only' => true]);
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDay()]);

    app(UpdateLot::class)->handle($lot, $seller, ['quantity' => '3', 'year' => '2020', 'pickup_only' => '1', 'title' => $lot->title]);

    expect(AuditLog::query()->where('action', 'lot.updated')->where('subject_id', $lot->id)->count())->toBe(0);

    app(UpdateLot::class)->handle($lot->fresh(), $seller, ['quantity' => '4']);

    $entry = AuditLog::query()->where('action', 'lot.updated')->where('subject_id', $lot->id)->sole();
    expect($entry->after['quantity'] ?? null)->toBe(4)->and($entry->before['quantity'] ?? null)->toBe(3);
});
