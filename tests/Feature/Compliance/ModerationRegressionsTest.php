<?php

declare(strict_types=1);

use App\Compliance\Actions\BlockUser;
use App\Compliance\Actions\DecideKyc;
use App\Enums\AuctionEventType;
use App\Enums\CompanyRole;
use App\Enums\KycStatus;
use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\AuctionEvent;
use App\Models\AutoBid;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\AuctionWithdrawn;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
| Regressies uit de codereview van moderatie en compliance: blokkeren stopt
| automatische biedingen, moderatie raakt alleen kavels die online staan,
| een afwijzing sluit de veiling netjes af, en de KvK-stap is voor wie het
| team beheert.
*/

beforeEach(fn () => Queue::fake());

function activeAutoBid(User $user, Auction $auction): AutoBid
{
    return AutoBid::query()->forceCreate([
        'auction_id' => $auction->id,
        'user_id' => $user->id,
        'max_amount_cents' => 200_000,
        'active' => true,
    ]);
}

it('zet de automatische biedingen uit van wie geblokkeerd of afgewezen wordt', function () {
    Notification::fake();
    $staff = User::factory()->create(['is_staff' => true]);
    $lot = Lot::factory()->create(['status' => LotStatus::Live]);
    $auction = Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDay()]);

    $blocked = User::factory()->verified()->create();
    $rejected = User::factory()->verified()->create();
    $bid1 = activeAutoBid($blocked, $auction);
    $bid2 = activeAutoBid($rejected, $auction);

    app(BlockUser::class)->block($blocked, $staff, 'Verdacht biedgedrag.');
    app(DecideKyc::class)->handle($rejected, $staff, false, 'Document onleesbaar.');

    expect($bid1->fresh()->active)->toBeFalse()
        ->and($bid2->fresh()->active)->toBeFalse()
        ->and($rejected->fresh()->kyc_status)->toBe(KycStatus::Rejected);
});

it('beoordeelt alleen kavels die online staan en niet twee keer', function () {
    Notification::fake();
    $staff = User::factory()->create(['is_staff' => true]);

    $sold = Lot::factory()->create(['status' => LotStatus::Sold]);
    Auction::factory()->create(['lot_id' => $sold->id, 'closed_at' => now()->subHour()]);

    $this->actingAs($staff)->from(route('admin.moderation'))
        ->post(route('admin.moderation.reject', $sold), ['reason' => 'Dit mag niet aangeboden worden.'])
        ->assertSessionHasErrors('lot');

    expect($sold->fresh()->status)->toBe(LotStatus::Sold);

    $live = Lot::factory()->create(['status' => LotStatus::Live]);
    Auction::factory()->create(['lot_id' => $live->id, 'ends_at' => now()->addDay()]);

    $this->actingAs($staff)->post(route('admin.moderation.approve', $live))->assertSessionHasNoErrors();
    $this->actingAs($staff)->from(route('admin.moderation'))->post(route('admin.moderation.approve', $live))->assertSessionHasErrors('lot');
});

it('sluit de veiling bij een afwijzing met een gebeurtenis en waarschuwt de bieders', function () {
    Notification::fake();
    $staff = User::factory()->create(['is_staff' => true]);
    $bidder = User::factory()->verified()->create();
    $other = User::factory()->verified()->create();

    $lot = Lot::factory()->create(['status' => LotStatus::Live]);
    $auction = Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDay(), 'last_sequence' => 2, 'bid_count' => 2]);
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $bidder->id, 'amount_cents' => 10_000, 'sequence' => 1]);
    Bid::factory()->create(['auction_id' => $auction->id, 'user_id' => $other->id, 'amount_cents' => 11_000, 'sequence' => 2]);
    $autoBid = activeAutoBid($other, $auction);

    $this->actingAs($staff)->post(route('admin.moderation.reject', $lot), ['reason' => 'Verboden categorie volgens de regels.'])->assertSessionHasNoErrors();

    $auction->refresh();
    $event = AuctionEvent::query()->where('auction_id', $auction->id)->where('type', AuctionEventType::Closed->value)->first();

    expect($auction->closed_at)->not->toBeNull()
        ->and($auction->last_sequence)->toBe(3)
        ->and($event?->sequence)->toBe(3)
        ->and($event?->payload['withdrawn'] ?? null)->toBeTrue()
        ->and($autoBid->fresh()->active)->toBeFalse();

    Notification::assertSentTo($bidder, AuctionWithdrawn::class);
    Notification::assertSentTo($other, AuctionWithdrawn::class);
});

it('laat alleen eigenaar of beheerder de KvK-stap doen en reset een geverifieerde identiteit niet', function () {
    $company = Company::factory()->create(['verified_at' => null]);
    $editor = User::factory()->create(['type' => 'business', 'company_id' => $company->id, 'company_role' => CompanyRole::Editor, 'email_verified_at' => now()]);

    $this->actingAs($editor)->post(route('verification.kvk'))->assertForbidden();
    expect($company->fresh()->kyb_requested_at)->toBeNull();

    $verified = User::factory()->verified()->create();
    $this->actingAs($verified)->post(route('verification.identity'))->assertRedirect(route('verification.overview'));

    expect($verified->fresh()->kyc_status)->toBe(KycStatus::Verified)
        ->and($verified->fresh()->can_bid)->toBeTrue();
});
