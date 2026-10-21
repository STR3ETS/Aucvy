<?php

declare(strict_types=1);

use App\Dashboard\ActivityFeed;
use App\Enums\KycStatus;
use App\Models\AiInsight;
use App\Models\Lot;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\VerificationDecided;

/*
| Het dashboard als werkplek: snelle acties bovenaan en één lijst met taken,
| meldingen en tickets, met tabs en tellers.
*/

it('zet taken, meldingen en tickets in één lijst met tellers per tab', function () {
    $user = User::factory()->verified()->create();
    Lot::factory()->anonymousDraft()->create(['seller_id' => $user->id, 'title' => 'Concept in de la']);
    $user->notify(new VerificationDecided(KycStatus::Verified));
    SupportTicket::factory()->create(['user_id' => $user->id, 'subject' => 'Vraag over transport']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Taken, meldingen en tickets')
        ->assertSee('Ticket #')
        ->assertSee('Vraag over transport')
        ->assertSee('Je identiteit is bevestigd')
        ->assertSee('staat klaar om afgemaakt te worden');

    $feed = app(ActivityFeed::class)->for($user, AiInsight::query()->where('user_id', $user->id)->current()->get());

    expect($feed['tabs']['task'][1])->toBe(1)
        ->and($feed['tabs']['notification'][1])->toBe(1)
        ->and($feed['tabs']['ticket'][1])->toBe(1)
        ->and($feed['tabs']['alles'][1])->toBe(3)
        ->and($feed['tabs']['ongelezen'][1])->toBe(3)
        ->and(collect($feed['items'])->pluck('kind')->sort()->values()->all())->toBe(['notification', 'task', 'ticket']);
});

it('telt een gelezen melding en een gesloten ticket niet als ongelezen', function () {
    $user = User::factory()->create();
    $user->notify(new VerificationDecided(KycStatus::Verified));
    $user->unreadNotifications()->update(['read_at' => now()]);
    SupportTicket::factory()->closed()->create(['user_id' => $user->id]);

    $feed = app(ActivityFeed::class)->for($user, collect());

    expect($feed['tabs']['alles'][1])->toBe(2)
        ->and($feed['tabs']['ongelezen'][1])->toBe(0);
});

it('linkt de snelle acties naar de veilingen en de wizard', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('lots.index'), escape: false)
        ->assertSee(route('sell'), escape: false);
});
