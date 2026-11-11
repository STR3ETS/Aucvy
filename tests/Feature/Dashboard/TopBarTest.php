<?php

declare(strict_types=1);

use App\Enums\KycStatus;
use App\Models\Lot;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\VerificationDecided;

/*
| De bovenbalk van het dashboard: het zoekveld opent het zoekvenster
| (Ctrl K) dat door alles van de klant zoekt, de bel schuift de meldingen
| rechts uit en de knop Vraag Ivy is weg (Ivy staat rechtsonder).
*/

/**
 * De titels van alle treffers uit een zoekantwoord, per groep.
 *
 * @param  array<string, mixed>  $json
 * @return array<string, list<string>>
 */
function searchTitles(array $json): array
{
    return collect($json['groups'])->mapWithKeys(fn (array $group): array => [$group['label'] => array_column($group['hits'], 'title')])->all();
}

it('toont het zoekveld en de bel als knoppen en geen knop Vraag Ivy meer', function () {
    $this->actingAs(User::factory()->verified()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Zoek in Aucvy')
        ->assertSee('Ctrl K')
        ->assertSee("new CustomEvent('palette-open')", escape: false)
        ->assertSee("new CustomEvent('notifs-open')", escape: false)
        ->assertSee('x-data="commandPalette(', escape: false)
        ->assertSee('x-data="notifDrawer(', escape: false)
        ->assertDontSee('>Vraag Ivy</button>', escape: false);
});

it('toont zonder zoekwoord de pagina\'s om heen te gaan', function () {
    $titles = searchTitles($this->actingAs(User::factory()->verified()->business()->create())->getJson(route('dashboard.search'))->assertOk()->json());

    expect(array_keys($titles))->toBe(['Ga naar'])
        ->and($titles['Ga naar'])->toContain('Mijn kavels', 'Bestellingen', 'Beveiliging', 'Organisatie');
});

it('zoekt in pagina\'s, eigen kavels, bestellingen en het aanbod', function () {
    $seller = User::factory()->verified()->business()->create();
    Lot::factory()->live()->create(['seller_id' => $seller->id, 'company_id' => $seller->company_id, 'title' => 'Koelvitrine Gram met glazen deur']);
    $bought = Lot::factory()->create(['title' => 'Koelcel Viessmann']);
    $order = Order::factory()->create(['buyer_id' => $seller->id, 'lot_id' => $bought->id]);
    // Van een ander en niet te koop: mag nergens opduiken.
    Lot::factory()->anonymousDraft()->create(['title' => 'Koelwerkbank concept van een ander']);

    $titles = searchTitles($this->actingAs($seller)->getJson(route('dashboard.search', ['q' => 'koel']))->assertOk()->json());

    expect($titles['Mijn kavels'])->toBe(['Koelvitrine Gram met glazen deur'])
        ->and($titles['Bestellingen'])->toBe(['Koelcel Viessmann'])
        ->and($titles['Te koop op Aucvy'])->toContain('Koelvitrine Gram met glazen deur', 'Alle resultaten voor "koel"')
        ->and(collect($titles)->flatten()->all())->not->toContain('Koelwerkbank concept van een ander');

    // Een bestelnummer vindt de bestelling ook.
    $byNumber = searchTitles($this->actingAs($seller)->getJson(route('dashboard.search', ['q' => $order->order_number]))->json());

    expect($byNumber['Bestellingen'])->toBe(['Koelcel Viessmann']);

    // Een pagina vind je op zijn naam.
    $pages = searchTitles($this->actingAs($seller)->getJson(route('dashboard.search', ['q' => 'uitbet']))->json());

    expect($pages['Pagina\'s'])->toContain('Uitbetalingen');
});

it('behandelt een procentteken in het zoekwoord als gewoon teken', function () {
    $seller = User::factory()->verified()->business()->create();
    Lot::factory()->live()->create(['seller_id' => $seller->id, 'company_id' => $seller->company_id, 'title' => 'Bureaustoel ergonomisch']);

    $titles = searchTitles($this->actingAs($seller)->getJson(route('dashboard.search', ['q' => '%%']))->assertOk()->json());

    expect($titles)->not->toHaveKey('Mijn kavels');
});

it('vraagt om inloggen voor het zoekvenster en het meldingenpaneel', function () {
    $this->getJson(route('dashboard.search', ['q' => 'koel']))->assertUnauthorized();
    $this->get(route('notifications.panel'))->assertRedirect(route('login'));
});

it('geeft het meldingenpaneel als fragment met meldingen en tickets, zonder taken', function () {
    $user = User::factory()->verified()->create();
    Lot::factory()->anonymousDraft()->create(['seller_id' => $user->id, 'title' => 'Concept in de la']);
    $user->notify(new VerificationDecided(KycStatus::Verified));
    SupportTicket::factory()->create(['user_id' => $user->id, 'subject' => 'Vraag over transport']);

    $this->actingAs($user)->get(route('notifications.panel'))
        ->assertOk()
        ->assertSee('Je identiteit is bevestigd')
        ->assertSee('Vraag over transport')
        ->assertDontSee('staat klaar om afgemaakt te worden')
        // Een fragment, geen hele pagina.
        ->assertDontSee('<html', escape: false);

    $this->actingAs(User::factory()->create())->get(route('notifications.panel'))
        ->assertOk()
        ->assertSee('Nog geen meldingen');
});
