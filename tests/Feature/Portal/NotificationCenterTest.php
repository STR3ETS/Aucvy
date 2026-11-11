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

    // De melding staat in de lade die vanaf de bel uitschuift; daar staat ook "Alles gelezen".
    $this->actingAs($user)->get(route('notifications.panel'))
        ->assertOk()
        ->assertSee('Heftruck Linde H25');
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Alles gelezen');

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

it('heeft geen eigen pagina Meldingen meer: de oude link opent de lade op het dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dashboard/meldingen')->assertRedirect(route('dashboard', ['meldingen' => 1]));

    // Het menu noemt Meldingen niet meer; de bel in de bovenbalk is de ingang.
    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('dash-bell', escape: false)
        ->assertSee('Oudere meldingen tonen')
        ->assertDontSee('Alle meldingen');

    expect(view()->exists('dashboard.notifications'))->toBeFalse();
});

it('toont eerst de laatste meldingen en de oudere pas als erom gevraagd wordt', function () {
    $user = User::factory()->create(['kyc_status' => KycStatus::Verified]);

    foreach (range(1, 18) as $number) {
        $this->travel(1)->minutes();
        $user->notify(new VerificationDecided(KycStatus::Rejected, 'Reden nummer '.$number.'.'));
    }

    $this->actingAs($user)->get(route('notifications.panel'))
        ->assertOk()
        ->assertSee('data-more="1"', escape: false)
        ->assertSee('Reden nummer 18.')
        ->assertDontSee('Reden nummer 2.');

    $this->actingAs($user)->get(route('notifications.panel', ['alles' => 1]))
        ->assertOk()
        ->assertSee('data-more="0"', escape: false)
        ->assertSee('Reden nummer 2.');
});
