<?php

declare(strict_types=1);

use App\Auctions\Actions\CloseAuction;
use App\Auctions\Jobs\CloseAuctionJob;
use App\Enums\DocumentKind;
use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\LotMedia;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/*
| Beheer van eigen kavels (briefing sectie 4): bewerken, pauzeren en
| hervatten, dupliceren, opnieuw plaatsen en bulk.
*/

function ownedLot(User $seller, LotStatus $status = LotStatus::Live, int $bids = 0): Lot
{
    $lot = Lot::factory()->create([
        'seller_id' => $seller->id,
        'status' => $status,
        'title' => 'Heftruck Linde H25',
        'description' => 'Nette heftruck, uit een magazijn dat sluit.',
        'published_at' => now()->subDay(),
    ]);

    Auction::factory()->create([
        'lot_id' => $lot->id,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(2),
        'original_ends_at' => now()->addDays(2),
        'bid_count' => $bids,
    ]);

    LotMedia::factory()->create(['lot_id' => $lot->id]);

    return $lot->fresh();
}

it('bewerkt omschrijving en kenmerken van een live kavel, met spoor', function () {
    $seller = User::factory()->verified()->create();
    $lot = ownedLot($seller);

    $this->actingAs($seller)->get(route('dashboard.lots.edit', $lot))->assertOk()->assertSee('Bewerken');

    $this->actingAs($seller)
        ->patch(route('dashboard.lots.update', $lot), [
            'title' => 'Heftruck Linde H25, 2019',
            'description' => 'Nette heftruck met nieuwe banden, uit een magazijn dat sluit.',
            'condition' => 'good',
            'brand' => 'Linde',
            'quantity' => 1,
            'pickup_only' => 1,
            'transport_from' => '125,50',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard.lots.edit', $lot));

    $lot->refresh();

    expect($lot->title)->toBe('Heftruck Linde H25, 2019')
        ->and($lot->description)->toContain('nieuwe banden')
        ->and($lot->transport_from_cents)->toBe(12_550)
        ->and($lot->field_sources['description']['source'])->toBe('seller');

    $entry = AuditLog::query()->where('action', 'lot.updated')->where('subject_id', $lot->id)->sole();

    expect($entry->before['title'])->toBe('Heftruck Linde H25')
        ->and($entry->after['title'])->toBe('Heftruck Linde H25, 2019');
});

it('zet de titel vast zodra er geboden is', function () {
    $seller = User::factory()->verified()->create();
    $lot = ownedLot($seller, bids: 3);

    $this->actingAs($seller)
        ->from(route('dashboard.lots.edit', $lot))
        ->patch(route('dashboard.lots.update', $lot), ['title' => 'Andere titel', 'quantity' => 1])
        ->assertSessionHasErrors('title');

    expect($lot->fresh()->title)->toBe('Heftruck Linde H25');
});

it('houdt andermans kavels buiten bereik', function () {
    $lot = ownedLot(User::factory()->verified()->create());
    $other = User::factory()->verified()->create();

    $this->actingAs($other)->get(route('dashboard.lots.edit', $lot))->assertNotFound();
    $this->actingAs($other)->patch(route('dashboard.lots.update', $lot), ['quantity' => 1])->assertNotFound();
    $this->actingAs($other)->post(route('dashboard.lots.pause', $lot))->assertNotFound();
});

it('pauzeert zonder biedingen, sluit intussen niet, en hervat met de resterende tijd', function () {
    Queue::fake();
    Carbon::setTestNow('2026-10-20 10:00:00');

    $seller = User::factory()->verified()->create();
    $lot = ownedLot($seller);
    $endsAt = $lot->auction->ends_at->copy();

    $this->actingAs($seller)->post(route('dashboard.lots.pause', $lot))->assertRedirect();

    $lot->refresh();

    expect($lot->status)->toBe(LotStatus::Paused)
        ->and($lot->status->isPublic())->toBeFalse()
        ->and($lot->status->acceptsBids())->toBeFalse()
        ->and($lot->auction->paused_at)->not->toBeNull();

    $this->get(route('lots.show', $lot))->assertNotFound();

    // Over de eindtijd heen terwijl hij stilstaat: niets sluit.
    Carbon::setTestNow($endsAt->copy()->addHour());
    (new CloseAuctionJob($lot->auction->id))->handle(app(CloseAuction::class));
    $this->artisan('auctions:close-overdue')->assertSuccessful();

    expect($lot->auction->fresh()->isClosed())->toBeFalse();

    // Drie uur gepauzeerd, dus drie uur erbij.
    Carbon::setTestNow('2026-10-20 13:00:00');
    $this->actingAs($seller)->post(route('dashboard.lots.resume', $lot))->assertRedirect();

    $lot->refresh();

    expect($lot->status)->toBe(LotStatus::Live)
        ->and($lot->auction->paused_at)->toBeNull()
        ->and($lot->auction->ends_at->equalTo($endsAt->copy()->addHours(3)))->toBeTrue()
        ->and(AuditLog::query()->whereIn('action', ['lot.paused', 'lot.resumed'])->count())->toBe(2);

    Queue::assertPushed(CloseAuctionJob::class);

    Carbon::setTestNow();
});

it('weigert te pauzeren als er al geboden is', function () {
    $seller = User::factory()->verified()->create();
    $lot = ownedLot($seller, bids: 2);

    $this->actingAs($seller)
        ->from(route('dashboard.lots.edit', $lot))
        ->post(route('dashboard.lots.pause', $lot))
        ->assertSessionHasErrors('lot');

    expect($lot->fresh()->status)->toBe(LotStatus::Live);
});

it('dupliceert naar een nieuw concept met foto, documenten en instellingen', function () {
    $seller = User::factory()->verified()->create();
    $lot = ownedLot($seller);
    LotDocument::factory()->create(['lot_id' => $lot->id, 'kind' => DocumentKind::Invoice]);

    $this->actingAs($seller)->post(route('dashboard.lots.duplicate', $lot))->assertRedirect();

    $copy = Lot::query()->where('status', LotStatus::Draft)->sole();

    expect($copy->id)->not->toBe($lot->id)
        ->and($copy->public_id)->not->toBe($lot->public_id)
        ->and($copy->title)->toBe($lot->title)
        ->and($copy->seller_id)->toBe($seller->id)
        ->and($copy->media()->count())->toBe(1)
        ->and($copy->documents()->count())->toBe(1)
        ->and($copy->draft_meta['sale_type'])->toBe('auction')
        ->and($copy->draft_meta['ownership_declared'])->toBeTrue()
        ->and($copy->draft_token)->not->toBeNull();

    $this->actingAs($seller)->get(route('sell', ['concept' => $copy->id]))->assertOk()->assertSee('#'.$copy->public_id);
});

it('plaatst een niet-verkochte kavel opnieuw als concept en laat het origineel staan', function () {
    $seller = User::factory()->verified()->create();
    $lot = ownedLot($seller, LotStatus::Unsold);

    $this->actingAs($seller)->get(route('dashboard.lots.edit', $lot))->assertOk()->assertSee('Opnieuw plaatsen');

    $this->actingAs($seller)->post(route('dashboard.lots.duplicate', $lot), ['relist' => 1])->assertRedirect();

    expect($lot->fresh()->status)->toBe(LotStatus::Unsold)
        ->and(Lot::query()->where('status', LotStatus::Draft)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'lot.relisted')->exists())->toBeTrue();
});

it('verwijdert in bulk alleen eigen concepten', function () {
    $seller = User::factory()->verified()->create();
    $draft = Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id]);
    $live = ownedLot($seller);
    $foreign = Lot::factory()->anonymousDraft()->create();

    $this->actingAs($seller)
        ->post(route('dashboard.lots.bulk'), ['action' => 'delete_drafts', 'lots' => [$draft->id, $live->id, $foreign->id]])
        ->assertRedirect(route('dashboard.lots'));

    expect(Lot::query()->whereKey($draft->id)->exists())->toBeFalse()
        ->and(Lot::query()->whereKey($live->id)->exists())->toBeTrue()
        ->and(Lot::query()->whereKey($foreign->id)->exists())->toBeTrue();
});

it('toont bewerken en opnieuw plaatsen vanuit Mijn kavels', function () {
    $seller = User::factory()->verified()->create();
    $live = ownedLot($seller);
    $unsold = ownedLot($seller, LotStatus::Unsold);

    $this->actingAs($seller)->get(route('dashboard.lots'))
        ->assertOk()
        ->assertSee(route('dashboard.lots.edit', $live), escape: false)
        ->assertSee(route('dashboard.lots.duplicate', $unsold), escape: false)
        ->assertSee('Opnieuw plaatsen');
});
