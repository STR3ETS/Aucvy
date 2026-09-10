<?php

declare(strict_types=1);

use App\Auctions\Jobs\CloseAuctionJob;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Lots\DraftAccess;
use App\Lots\PublishLot;
use App\Models\Category;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function draftFor(?User $seller = null, array $meta = []): Lot
{
    return Lot::factory()->anonymousDraft()->create([
        'seller_id' => $seller?->id,
        'status' => LotStatus::Draft,
        'title' => 'Complete horecakeuken',
        'slug' => null,
        'category_id' => Category::factory()->create()->id,
        'draft_meta' => $meta ?: [
            'sale_type' => 'auction',
            'start_price_cents' => 80_000,
            'duration_days' => 7,
        ],
    ]);
}

describe('publiceren', function () {
    beforeEach(function () {
        Queue::fake();
        $this->seller = User::factory()->verified()->create();
    });

    it('maakt van het concept een lopende veiling', function () {
        $lot = draftFor($this->seller);

        $published = app(PublishLot::class)->handle($lot, $this->seller);

        expect($published->status)->toBe(LotStatus::Live)
            ->and($published->sale_type)->toBe(SaleType::Auction)
            ->and($published->slug)->toEndWith('-'.$lot->public_id)
            ->and($published->draft_meta)->toBeNull()
            ->and($published->draft_token)->toBeNull();

        $auction = $published->auction;

        expect($auction->start_price_cents)->toBe(80_000)
            // De staffel wordt gekopieerd uit config en daarna nooit meer
            // gelezen (hfst. 5). toEqual, niet toBe: MySQL herordent de keys
            // binnen een JSON-object en dat is geen inhoudelijk verschil.
            ->and($auction->increment_table)->toEqual(config('aucvy.auction.increment_table'))
            ->and($auction->ends_at->diffInDays($auction->starts_at, absolute: true))->toBe(7.0);
    });

    it('plant de sluiting in', function () {
        $lot = draftFor($this->seller);

        app(PublishLot::class)->handle($lot, $this->seller);

        Queue::assertPushed(CloseAuctionJob::class);
    });

    it('is daarna publiek zichtbaar en biedbaar', function () {
        $lot = draftFor($this->seller);
        $published = app(PublishLot::class)->handle($lot, $this->seller);

        $this->get(route('lots.show', $published))
            ->assertOk()
            ->assertSee('Complete horecakeuken');
    });

    it('weigert een niet-geverifieerde verkoper', function () {
        // "KVK-controle en identiteitscheck voordat een kavel live kan" - de
        // gate zit in de actie, niet alleen in de UI.
        $unverified = User::factory()->create();
        $lot = draftFor($unverified);

        expect(fn () => app(PublishLot::class)->handle($lot, $unverified))
            ->toThrow(ValidationException::class);
    });

    it('weigert publiceren zonder titel of categorie', function () {
        $lot = draftFor($this->seller);
        $lot->update(['title' => null]);

        expect(fn () => app(PublishLot::class)->handle($lot->fresh(), $this->seller))
            ->toThrow(ValidationException::class);
    });

    it('weigert een veiling zonder startprijs', function () {
        $lot = draftFor($this->seller, ['sale_type' => 'auction']);

        expect(fn () => app(PublishLot::class)->handle($lot, $this->seller))
            ->toThrow(ValidationException::class);
    });

    it('weigert een afslag met een bodem boven de start', function () {
        $lot = draftFor($this->seller, [
            'sale_type' => 'dutch',
            'dutch_start_cents' => 100_000,
            'dutch_floor_cents' => 200_000,
            'dutch_step_cents' => 5_000,
            'dutch_interval_s' => 20,
        ]);

        expect(fn () => app(PublishLot::class)->handle($lot, $this->seller))
            ->toThrow(ValidationException::class);
    });

    it('publiceert een complete afslag met de juiste instellingen', function () {
        $lot = draftFor($this->seller, [
            'sale_type' => 'dutch',
            'dutch_start_cents' => 2_000_000,
            'dutch_floor_cents' => 50_000,
            'dutch_step_cents' => 25_000,
            'dutch_interval_s' => 20,
        ]);

        $published = app(PublishLot::class)->handle($lot, $this->seller);

        expect($published->sale_type)->toBe(SaleType::Dutch)
            ->and($published->auction->dutch_floor_cents)->toBe(50_000)
            ->and($published->auction->current_price_cents)->toBe(2_000_000);
    });

    it('kan niet twee keer', function () {
        $lot = draftFor($this->seller);
        app(PublishLot::class)->handle($lot, $this->seller);

        expect(fn () => app(PublishLot::class)->handle($lot->fresh(), $this->seller))
            ->toThrow(ValidationException::class);
    });
});

describe('via het endpoint', function () {
    beforeEach(function () {
        // Cookies gaan bij *Json-requests alleen mee met withCredentials().
        $this->withCredentials();
    });

    it('vraagt eerst om in te loggen', function () {
        // Hier - en niet eerder - komt de accountvraag (hfst. 13.4).
        $this->postJson('/api/drafts');
        $lot = Lot::sole();

        $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
            ->postJson("/api/drafts/{$lot->id}/publish")
            ->assertStatus(401)
            ->assertJsonPath('login_url', route('login'));

        expect($lot->fresh()->status)->toBe(LotStatus::Draft);
    });

    it('publiceert voor een ingelogde geverifieerde verkoper', function () {
        Queue::fake();

        $seller = User::factory()->verified()->create();
        $lot = draftFor($seller);

        $this->actingAs($seller)
            ->postJson("/api/drafts/{$lot->id}/publish")
            ->assertOk()
            ->assertJsonPath('published', true);

        expect($lot->fresh()->status)->toBe(LotStatus::Live);
    });
});
