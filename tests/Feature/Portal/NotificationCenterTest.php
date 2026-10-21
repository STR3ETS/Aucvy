<?php

declare(strict_types=1);

use App\Auctions\Events\Outbid;
use App\Enums\KycStatus;
use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\OutbidMail;
use App\Notifications\VerificationDecided;

/*
| Het meldingencentrum (briefing sectie 3): wat per mail of push gaat, staat
| ook in-app; openen markeert als gelezen en gaat door naar het onderwerp.
*/

it('bewaart een overboden-melding in-app en toont hem met ongelezen teller', function () {
    $user = User::factory()->create();
    $lot = Lot::factory()->create(['status' => LotStatus::Live, 'title' => 'Heftruck Linde H25']);
    $auction = Auction::factory()->create(['lot_id' => $lot->id, 'current_price_cents' => 300_000]);

    $user->notify(new OutbidMail(new Outbid($auction, $user, 300_000)));

    expect($user->unreadNotifications()->count())->toBe(1)
        ->and($user->notifications()->first()->data['kind'])->toBe('outbid')
        ->and($user->notifications()->first()->data['url'])->toBe(route('lots.show', $lot));

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Heftruck Linde H25')
        ->assertSee('Alles gelezen');

    // De bel in de bovenbalk telt mee.
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('notif-count', escape: false);
});

it('markeert een melding als gelezen bij het openen en gaat door naar het onderwerp', function () {
    $user = User::factory()->create(['kyc_status' => KycStatus::Verified]);
    $user->notify(new VerificationDecided(KycStatus::Verified));

    $notification = $user->notifications()->first();

    $this->actingAs($user)->get(route('notifications.open', $notification->id))
        ->assertRedirect(route('lots.index'));

    expect($notification->fresh()->read_at)->not->toBeNull();

    // Niet van jou: niet te openen.
    $this->actingAs(User::factory()->create())->get(route('notifications.open', $notification->id))->assertNotFound();
});

it('markeert alles als gelezen', function () {
    $user = User::factory()->create();
    $user->notify(new VerificationDecided(KycStatus::Verified));
    $user->notify(new VerificationDecided(KycStatus::Rejected, 'Document onleesbaar.'));

    $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});
