<?php

declare(strict_types=1);

use App\Enums\DocumentKind;
use App\Lots\DraftAccess;
use App\Lots\Preflight;
use App\Lots\PublishLot;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\LotMedia;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

/*
| De preflight van stap 8 (briefing sectie 5): verplichte data, eigendom,
| verboden categorie en KYB. Eén lijst voor de funnel en voor PublishLot.
*/

beforeEach(function () {
    Queue::fake();
    $this->withCredentials();
});

function readyDraft(User $seller, array $overrides = []): Lot
{
    $lot = Lot::factory()->anonymousDraft()->create([
        'seller_id' => $seller->id,
        'title' => 'Heftruck Linde H25',
        'category_id' => Category::factory()->create()->id,
        'draft_meta' => ['sale_type' => 'auction', 'start_price_cents' => 250_000, 'ownership_declared' => true, 'warranty' => 'none'],
        ...$overrides,
    ]);

    LotMedia::factory()->create(['lot_id' => $lot->id]);

    return $lot;
}

function checkOf(array $preflight, string $key): array
{
    return collect($preflight['checks'])->firstWhere('key', $key);
}

it('keurt een compleet concept goed en noemt elk punt', function () {
    $seller = User::factory()->verified()->business()->create();
    $lot = readyDraft($seller);

    $preflight = app(Preflight::class)->for($lot, $seller);

    expect($preflight['ok'])->toBeTrue()
        ->and($preflight['blocking'])->toBe([])
        ->and(collect($preflight['checks'])->pluck('key')->all())
        ->toBe(['title', 'category', 'forbidden', 'photos', 'condition', 'description', 'pricing', 'price_order', 'ownership', 'unit', 'warranty', 'documents_authentic', 'confirmed', 'permission']);
});

it('eist een eigendomsverklaring of een eigendomsbewijs', function () {
    $seller = User::factory()->verified()->business()->create();
    $lot = readyDraft($seller, ['draft_meta' => ['sale_type' => 'auction', 'start_price_cents' => 250_000, 'warranty' => 'none', 'documents_authentic' => true]]);

    $preflight = app(Preflight::class)->for($lot, $seller);

    expect($preflight['ok'])->toBeFalse()
        ->and($preflight['blocking'])->toBe(['ownership'])
        ->and(checkOf($preflight, 'ownership')['step'])->toBe(1);

    expect(fn () => app(PublishLot::class)->handle($lot, $seller))->toThrow(ValidationException::class);

    // Een factuur als document geldt ook als bewijs.
    LotDocument::factory()->create(['lot_id' => $lot->id, 'kind' => DocumentKind::Invoice]);

    expect(app(Preflight::class)->for($lot->fresh(), $seller)['ok'])->toBeTrue();
});

it('eist minstens één foto', function () {
    $seller = User::factory()->verified()->business()->create();
    $lot = readyDraft($seller);
    $lot->media()->delete();

    $preflight = app(Preflight::class)->for($lot->fresh(), $seller);

    expect($preflight['blocking'])->toBe(['photos'])
        ->and(fn () => app(PublishLot::class)->handle($lot->fresh(), $seller))->toThrow(ValidationException::class);
});

it('blokkeert een verboden categorie, ook via de ouder', function () {
    config()->set('aucvy.listings.forbidden_categories', ['wapens']);

    $seller = User::factory()->verified()->business()->create();
    $parent = Category::factory()->create(['slug' => 'wapens', 'name' => 'Wapens']);
    $child = Category::factory()->create(['parent_id' => $parent->id, 'slug' => 'jachtgeweren', 'name' => 'Jachtgeweren']);
    $lot = readyDraft($seller, ['category_id' => $child->id]);

    $preflight = app(Preflight::class)->for($lot, $seller);

    expect($preflight['blocking'])->toBe(['forbidden'])
        ->and(checkOf($preflight, 'forbidden')['hint'])->toContain('mag niet');

    expect(fn () => app(PublishLot::class)->handle($lot, $seller))->toThrow(ValidationException::class);
});

it('laat de lijst verboden categorieën leeg tot Raphael hem vaststelt', function () {
    expect(config('aucvy.listings.forbidden_categories'))->toBe([]);
});

it('noemt de ontbrekende bedrijfscontrole met een route naar de verificatie', function () {
    $seller = User::factory()->verified()->business()->create();
    $seller->company->forceFill(['verified_at' => null])->save();
    $lot = readyDraft($seller->fresh());

    $preflight = app(Preflight::class)->for($lot, $seller->fresh());
    $check = checkOf($preflight, 'permission');

    expect($preflight['blocking'])->toBe(['permission'])
        ->and($check['route'])->toBe(route('verification.overview'))
        ->and($check['hint'])->toContain('KvK');
});

it('zet de accountvraag als punt voor wie niet is ingelogd, en de rest blijft groen', function () {
    $this->postJson('/api/drafts')->assertCreated();
    $lot = Lot::sole();

    $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
        ->patchJson("/api/drafts/{$lot->id}", ['title' => 'Steiger', 'ownership_declared' => true])
        ->assertOk()
        ->assertJsonPath('preflight.ok', false)
        ->assertJsonPath('draft.meta.ownership_declared', true);

    $preflight = $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
        ->getJson("/api/drafts/{$lot->id}")
        ->json('preflight');

    expect(checkOf($preflight, 'account')['ok'])->toBeFalse()
        ->and(checkOf($preflight, 'ownership')['ok'])->toBeTrue()
        ->and(checkOf($preflight, 'title')['ok'])->toBeTrue();
});

it('markeert conditie en omschrijving als aanbeveling, niet als blokkade', function () {
    $seller = User::factory()->verified()->business()->create();
    $lot = readyDraft($seller, ['condition' => null, 'description' => 'kort']);

    $preflight = app(Preflight::class)->for($lot, $seller);

    expect($preflight['ok'])->toBeTrue()
        ->and(checkOf($preflight, 'condition')['ok'])->toBeFalse()
        ->and(checkOf($preflight, 'condition')['blocking'])->toBeFalse()
        ->and(checkOf($preflight, 'description')['blocking'])->toBeFalse();
});
