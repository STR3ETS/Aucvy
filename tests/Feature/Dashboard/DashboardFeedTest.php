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
| Het dashboard als werkplek: snelle acties bovenaan en het team met per
| teamlid hoeveel er wacht (de taken zelf staan in het gesprek met dat
| teamlid). Meldingen en tickets schuiven uit vanaf de bel in de bovenbalk.
*/

it('zet meldingen en tickets achter de bel en taken bij het teamlid in het paneel', function () {
    $user = User::factory()->verified()->create();
    Lot::factory()->anonymousDraft()->create(['seller_id' => $user->id, 'title' => 'Concept in de la']);
    $user->notify(new VerificationDecided(KycStatus::Verified));
    SupportTicket::factory()->create(['user_id' => $user->id, 'subject' => 'Vraag over transport']);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        // De lijst met meldingen staat niet meer op het overzicht zelf.
        ->assertDontSee('Meldingen en tickets')
        ->assertDontSee('Vraag over transport')
        ->assertDontSee('Je identiteit is bevestigd')
        ->assertSeeInOrder(['Je team', 'Eén ding wacht op je'])
        ->assertSee('1 punt open')
        ->assertDontSee('Taken, meldingen en tickets')
        // De taak zelf staat niet op het dashboard: die staat in het gesprek met het teamlid.
        ->assertDontSee('staat klaar om afgemaakt te worden');

    // Ze staan in het paneel dat vanaf de bel uitschuift.
    $this->actingAs($user)->get(route('notifications.panel'))
        ->assertOk()
        ->assertSee('Ticket #')
        ->assertSee('Vraag over transport')
        ->assertSee('Je identiteit is bevestigd');

    $owner = collect($this->actingAs($user)->getJson(route('ivy.show'))->json('agents'))->firstWhere('count', 1)['key'];

    $this->actingAs($user)->getJson(route('ivy.show', ['agent' => $owner]))
        ->assertOk()
        ->assertJsonPath('agent.key', $owner)
        ->assertJsonPath('tasks.0.title', fn (string $title): bool => str_contains($title, 'staat klaar om afgemaakt te worden'));

    $feed = app(ActivityFeed::class)->for($user, AiInsight::query()->where('user_id', $user->id)->current()->get());

    expect($feed['tabs']['task'][1])->toBe(1)
        ->and($feed['tabs']['notification'][1])->toBe(1)
        ->and($feed['tabs']['ticket'][1])->toBe(1)
        ->and($feed['tabs']['alles'][1])->toBe(3)
        ->and($feed['tabs']['ongelezen'][1])->toBe(3)
        ->and(collect($feed['items'])->pluck('kind')->sort()->values()->all())->toBe(['notification', 'task', 'ticket']);
});

it('toont per teamlid hoeveel er wacht en opent het gesprek met een klik', function () {
    $user = User::factory()->verified()->create();
    foreach (range(1, 6) as $number) {
        AiInsight::create([
            'user_id' => $user->id,
            'agent' => 'ivy',
            'type' => 'weekly',
            'title' => 'Taak '.$number,
            'severity' => 'info',
            'action_route' => 'dashboard.lots',
            'action_label' => 'Bekijken',
            'valid_until' => now()->addDay(),
        ]);
    }

    $content = $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('6 punten open')
        ->assertSee('6 dingen wachten op je')
        ->assertSee("detail: { agent: 'ivy' }", escape: false)
        ->assertSee("detail: { agent: 'finn' }", escape: false)
        ->assertDontSee('Taak 1')
        ->getContent();

    // Zes teamleden, één met een teller.
    expect(substr_count($content, 'class="d-member'))->toBeGreaterThanOrEqual(6)
        ->and(substr_count($content, 'class="d-member-count"'))->toBe(1);
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

it('zet wat nog niet gelezen is bovenaan, ook als het ouder is', function () {
    $user = User::factory()->verified()->create();

    $this->travelTo(now()->subDays(3));
    $user->notify(new VerificationDecided(KycStatus::Verified));
    $this->travelBack();

    // Een nieuwere melding die al gelezen is.
    $user->notify(new VerificationDecided(KycStatus::Rejected));
    $user->notifications()->latest()->first()->markAsRead();

    $items = collect(app(ActivityFeed::class)->for($user, collect())['items']);

    expect($items->pluck('unread')->all())->toBe([true, false])
        ->and($items[0]['at']->lt($items[1]['at']))->toBeTrue();
});
