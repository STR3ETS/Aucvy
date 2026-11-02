<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Ivy\Jobs\RunAiTask;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotMedia;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
| Auction Studio (briefing sectie 4 en 1): een veiling als verzameling
| kavels met een sluitschema, bezichtiging en eigen voorwaarden; de
| kavels gaan door de gewone publicatie en krijgen hun sluittijd van de
| veiling.
*/

beforeEach(fn () => Queue::fakeExcept([RunAiTask::class]));

function saleDraft(User $seller, string $title): Lot
{
    $lot = Lot::factory()->anonymousDraft()->create([
        'seller_id' => $seller->id,
        'status' => LotStatus::Draft,
        'title' => $title,
        'slug' => null,
        'category_id' => Category::factory()->create()->id,
        'draft_meta' => ['ownership_declared' => true, 'warranty' => 'none', 'sale_type' => 'auction', 'start_price_cents' => 50_000, 'duration_days' => 7],
    ]);

    LotMedia::factory()->create(['lot_id' => $lot->id]);

    return $lot;
}

it('plant een veiling, zet kavels erin, publiceert en laat de kavels na elkaar sluiten', function () {
    $seller = User::factory()->verified()->business()->create();
    $endsAt = now()->addDays(3)->setTime(20, 0);

    $this->actingAs($seller)
        ->post(route('dashboard.sales.store'), [
            'title' => 'Faillissementsveiling Restaurant De Molen',
            'description' => 'De complete inventaris.',
            'ends_at' => $endsAt->timezone(config('aucvy.display_timezone'))->format('Y-m-d\TH:i'),
            'closing_gap_seconds' => 120,
            'viewing_at' => $endsAt->copy()->subDay()->format('Y-m-d\TH:i'),
            'viewing_note' => 'Op afspraak',
            'pickup_from' => $endsAt->copy()->addDay()->format('Y-m-d'),
            'pickup_until' => $endsAt->copy()->addDays(3)->format('Y-m-d'),
            'terms' => 'Zelf demonteren.',
        ])
        ->assertSessionHasNoErrors();

    $sale = Sale::query()->sole();
    expect($sale->ends_at->equalTo($endsAt))->toBeTrue()->and($sale->slug)->toStartWith('faillissementsveiling-restaurant-de-molen-');

    $first = saleDraft($seller, 'Koelwerkbank RVS');
    $second = saleDraft($seller, 'Frituur dubbel');

    $this->actingAs($seller)->post(route('dashboard.sales.attach', $sale), ['lots' => [$first->id, $second->id]])->assertSessionHasNoErrors();
    expect($first->fresh()->sale_position)->toBe(0)->and($second->fresh()->sale_position)->toBe(1);

    $this->get(route('sales.show', $sale))->assertNotFound();

    $this->actingAs($seller)->post(route('dashboard.sales.publish', $sale))->assertSessionHasNoErrors();
    $this->get(route('sales.show', $sale))->assertOk()->assertSee('Faillissementsveiling Restaurant De Molen')->assertSee('Op afspraak')->assertSee('De kavels volgen binnenkort');

    $this->actingAs($seller)->post(route('dashboard.sales.publish-drafts', $sale))->assertSessionHasNoErrors();

    $first->refresh();
    $second->refresh();

    expect($first->status)->toBe(LotStatus::Live)
        ->and($second->status)->toBe(LotStatus::Live)
        ->and($first->auction->ends_at->equalTo($endsAt))->toBeTrue()
        ->and($second->auction->ends_at->equalTo($endsAt->copy()->addSeconds(120)))->toBeTrue();

    $this->get(route('sales.show', $sale))->assertOk()->assertSee('Koelwerkbank RVS')->assertSee('Frituur dubbel');
    $this->get(route('lots.show', $first))->assertOk()->assertSee('Onderdeel van de veiling')->assertSee('Faillissementsveiling Restaurant De Molen');
    $this->actingAs($seller)->get(route('dashboard.sales'))->assertOk()->assertSee('Faillissementsveiling Restaurant De Molen')->assertSee('Loopt');
});

it('weigert publiceren zonder kavels of met een te vroege sluiting en houdt vreemden buiten', function () {
    $seller = User::factory()->verified()->business()->create();
    $sale = Sale::factory()->create(['seller_id' => $seller->id]);

    $this->actingAs($seller)->from(route('dashboard.sales.edit', $sale))->post(route('dashboard.sales.publish', $sale))->assertSessionHasErrors('sale');

    $draft = saleDraft($seller, 'Oven');
    $this->actingAs($seller)->post(route('dashboard.sales.attach', $sale), ['lots' => [$draft->id]])->assertSessionHasNoErrors();

    $sale->forceFill(['ends_at' => now()->addMinutes(10)])->save();
    $this->actingAs($seller)->from(route('dashboard.sales.edit', $sale))->post(route('dashboard.sales.publish', $sale))->assertSessionHasErrors('ends_at');

    // Een kavel die al live is kan er niet in.
    $live = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Live]);
    $this->actingAs($seller)->from(route('dashboard.sales.edit', $sale))->post(route('dashboard.sales.attach', $sale), ['lots' => [$live->id]])->assertSessionHasErrors('lot');

    $stranger = User::factory()->verified()->create();
    $this->actingAs($stranger)->get(route('dashboard.sales.edit', $sale))->assertNotFound();
    $this->actingAs($stranger)->post(route('dashboard.sales.attach', $sale), ['lots' => [$draft->id]])->assertNotFound();
    $this->actingAs($seller)->get(route('dashboard.sales.edit', $sale))->assertOk()->assertSee('Oven')->assertSee('Veiling publiceren');
});

it('schuift kavels zonder bod mee met een nieuwe sluiting en annuleert alleen zonder biedingen', function () {
    $seller = User::factory()->verified()->business()->create();
    $sale = Sale::factory()->published()->create(['seller_id' => $seller->id, 'ends_at' => now()->addDays(2)->setTime(19, 0), 'closing_gap_seconds' => 300]);

    $quiet = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Live, 'sale_id' => $sale->id, 'sale_position' => 0]);
    $busy = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Live, 'sale_id' => $sale->id, 'sale_position' => 1]);
    Auction::factory()->create(['lot_id' => $quiet->id, 'ends_at' => $sale->closingFor(0), 'bid_count' => 0]);
    Auction::factory()->create(['lot_id' => $busy->id, 'ends_at' => $sale->closingFor(1), 'bid_count' => 2]);

    $newEnd = now()->addDays(4)->setTime(19, 0);

    $this->actingAs($seller)
        ->patch(route('dashboard.sales.update', $sale), ['title' => $sale->title, 'ends_at' => $newEnd->timezone(config('aucvy.display_timezone'))->format('Y-m-d\TH:i'), 'closing_gap_seconds' => 300])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', fn (string $status): bool => str_contains($status, '1 kavels kregen') && str_contains($status, 'houden hun sluittijd'));

    expect($quiet->auction->fresh()->ends_at->equalTo($newEnd))->toBeTrue()
        ->and($busy->auction->fresh()->ends_at->equalTo($sale->closingFor(1)))->toBeTrue();

    $this->actingAs($seller)->from(route('dashboard.sales.edit', $sale))->post(route('dashboard.sales.cancel', $sale))->assertSessionHasErrors('sale');

    $busy->auction->forceFill(['bid_count' => 0])->save();
    $this->actingAs($seller)->post(route('dashboard.sales.cancel', $sale))->assertRedirect(route('dashboard.sales'));

    expect($sale->fresh()->isCancelled())->toBeTrue();
    $this->get(route('sales.show', $sale))->assertNotFound();
});
