<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceBid;
use App\Enums\KycStatus;
use App\Lots\PublishLot;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotMedia;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/*
| Elke overgang die geld, status of eigendom raakt laat een regel achter in
| het auditlog (briefing sectie 9 en 11), en die regels zijn bewijs: ze
| worden nooit bijgewerkt of verwijderd.
*/

// De sluitjob hoort in de agenda, niet direct op de synchrone testqueue.
beforeEach(fn () => Queue::fake());

it('legt een bod vast met bieder, veiling en bedrag', function () {
    $lot = Lot::factory()->live()->create();
    $auction = Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);
    $bidder = User::factory()->verified()->create();

    $bid = app(PlaceBid::class)->handle($auction, $bidder, 110_000, (string) Str::uuid());

    $entry = AuditLog::query()->where('action', 'bid.placed')->sole();

    expect($entry->actor_id)->toBe($bidder->id)
        ->and($entry->subject_type)->toBe($bid->getMorphClass())
        ->and($entry->subject_id)->toBe($bid->id)
        ->and($entry->after['amount_cents'])->toBe(110_000)
        ->and($entry->after['auction_id'])->toBe($auction->id);
});

it('legt een publicatie vast met verkoopvorm en veiling', function () {
    $seller = User::factory()->verified()->business()->create();
    $lot = Lot::factory()->anonymousDraft()->create([
        'seller_id' => $seller->id,
        'title' => 'Heftruck Linde H25',
        'category_id' => Category::factory()->create()->id,
        'draft_meta' => ['sale_type' => 'auction', 'start_price_cents' => 250_000, 'ownership_declared' => true],
    ]);
    LotMedia::factory()->create(['lot_id' => $lot->id]);

    app(PublishLot::class)->handle($lot, $seller);

    $entry = AuditLog::query()->where('action', 'lot.published')->sole();

    expect($entry->actor_id)->toBe($seller->id)
        ->and($entry->subject_id)->toBe($lot->id)
        ->and($entry->after['sale_type'])->toBe('auction');
});

it('legt een aangevraagde identiteitscontrole vast met de oude en nieuwe status', function () {
    $user = User::factory()->create(['kyc_status' => KycStatus::Unverified]);

    $this->actingAs($user)->post('/verificatie/identiteit')->assertRedirect();

    $entry = AuditLog::query()->where('action', 'user.kyc_changed')->sole();

    expect($entry->before)->toBe(['status' => 'unverified'])
        ->and($entry->after['status'])->toBe('pending')
        ->and($entry->actor_id)->toBe($user->id);
});

it('weigert een auditregel bij te werken of te verwijderen', function () {
    $user = User::factory()->create();
    $entry = Audit::record('test.action', $user, $user, after: ['x' => 1]);

    expect(fn () => $entry->update(['action' => 'anders']))->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class)
        ->and(AuditLog::query()->whereKey($entry->id)->value('action'))->toBe('test.action');
});
