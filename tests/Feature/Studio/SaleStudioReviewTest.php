<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\LotStatus;
use App\Enums\SaleState;
use App\Ivy\Jobs\RunAiTask;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Company;
use App\Models\Lot;
use App\Models\LotMedia;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/*
| Regressies uit de codereview van Auction Studio: eigenaar blijft eigenaar
| als een collega publiceert, een verschoven sluiting kan niets per direct
| sluiten, en de status volgt de echte biedrondes.
*/

beforeEach(fn () => Queue::fakeExcept([RunAiTask::class]));

function reviewDraft(User $seller, string $title): Lot
{
    $lot = Lot::factory()->anonymousDraft()->create([
        'seller_id' => $seller->id,
        'company_id' => $seller->company_id,
        'status' => LotStatus::Draft,
        'title' => $title,
        'slug' => null,
        'category_id' => Category::factory()->create()->id,
        'draft_meta' => ['ownership_declared' => true, 'warranty' => 'none', 'sale_type' => 'auction', 'start_price_cents' => 50_000, 'duration_days' => 7],
    ]);

    LotMedia::factory()->create(['lot_id' => $lot->id]);

    return $lot;
}

it('laat een redacteur de concepten van de eigenaar publiceren zonder ze over te nemen', function () {
    $company = Company::factory()->verified()->create();
    $owner = User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => CompanyRole::Owner, 'type' => 'business']);
    $editor = User::factory()->verified()->create(['company_id' => $company->id, 'company_role' => CompanyRole::Editor, 'type' => 'business']);

    $sale = Sale::factory()->published()->create(['seller_id' => $owner->id, 'company_id' => $company->id, 'ends_at' => now()->addDays(3)->setTime(20, 0)]);
    $lot = reviewDraft($owner, 'Koelwerkbank van de eigenaar');
    $lot->forceFill(['sale_id' => $sale->id, 'sale_position' => 0])->save();

    $this->actingAs($editor)->post(route('dashboard.sales.publish-drafts', $sale))->assertSessionHasNoErrors();

    $lot->refresh();
    expect($lot->status)->toBe(LotStatus::Live)
        ->and($lot->seller_id)->toBe($owner->id)
        ->and($lot->company_id)->toBe($company->id);
});

it('weigert een eerste sluiting binnen een uur en verschuift niets bij een ongepubliceerde veiling', function () {
    $seller = User::factory()->verified()->create();
    $sale = Sale::factory()->create(['seller_id' => $seller->id]);

    $this->actingAs($seller)->from(route('dashboard.sales.edit', $sale))
        ->patch(route('dashboard.sales.update', $sale), ['title' => $sale->title, 'ends_at' => now()->addMinutes(10)->format('Y-m-d\TH:i'), 'closing_gap_seconds' => 120])
        ->assertSessionHasErrors('ends_at');

    // Een kavel die los gepubliceerd is in een nog niet gepubliceerde veiling houdt zijn eigen looptijd.
    $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Live, 'sale_id' => $sale->id, 'sale_position' => 0]);
    $auction = Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDays(7), 'bid_count' => 0]);
    $ownEnd = $auction->ends_at;

    $this->actingAs($seller)
        ->patch(route('dashboard.sales.update', $sale), ['title' => 'Nieuwe naam', 'ends_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'closing_gap_seconds' => 120])
        ->assertSessionHasNoErrors();

    expect($auction->fresh()->ends_at->equalTo($ownEnd))->toBeTrue();

    // Concepten publiceren kan pas als de veiling gepubliceerd is.
    $this->actingAs($seller)->from(route('dashboard.sales.edit', $sale))->post(route('dashboard.sales.publish-drafts', $sale))->assertSessionHasErrors('sale');

    // Publiceren geeft de losse kavel alsnog het schema.
    reviewDraft($seller, 'Tweede')->forceFill(['sale_id' => $sale->id, 'sale_position' => 1])->save();
    $this->actingAs($seller)->post(route('dashboard.sales.publish', $sale))->assertSessionHasNoErrors();

    expect($auction->fresh()->ends_at->equalTo($sale->fresh()->closingFor(0)))->toBeTrue();
});

it('negeert een dubbele toevoeging en telt een verlengde biedronde als lopend', function () {
    $seller = User::factory()->verified()->create();
    $sale = Sale::factory()->published()->create(['seller_id' => $seller->id, 'ends_at' => now()->addHours(2), 'closing_gap_seconds' => 60]);
    $draft = reviewDraft($seller, 'Dubbel');

    $this->actingAs($seller)->post(route('dashboard.sales.attach', $sale), ['lots' => [$draft->id]])->assertSessionHasNoErrors();
    $this->actingAs($seller)->post(route('dashboard.sales.attach', $sale), ['lots' => [$draft->id]])->assertSessionHasNoErrors();

    expect($sale->lots()->count())->toBe(1)->and($draft->fresh()->sale_position)->toBe(0);

    // Het schema zegt gesloten, maar antisnipe tilde de biedronde eroverheen.
    $this->travelTo(now()->addHours(3));
    $live = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Live, 'sale_id' => $sale->id, 'sale_position' => 1]);
    Auction::factory()->create(['lot_id' => $live->id, 'ends_at' => now()->addMinutes(5), 'closed_at' => null]);

    expect($sale->fresh()->state())->toBe(SaleState::Live);

    $live->auction->forceFill(['closed_at' => now()])->save();
    expect($sale->fresh()->state())->toBe(SaleState::Closed);
});
