<?php

declare(strict_types=1);

use App\Lots\DraftAccess;
use App\Models\Lot;
use App\Models\User;

/*
| Conceptoverzicht en hervatten (briefing sectie 5): meerdere concepten
| naast elkaar, elk op zijn eigen id te openen, ook op een ander apparaat.
*/

it('opent een specifiek concept en toont de andere', function () {
    $seller = User::factory()->verified()->create();
    $first = Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id, 'title' => 'Koelcel 20 m3', 'completeness_score' => 61]);
    $second = Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id, 'title' => 'Rolsteiger 8 meter', 'completeness_score' => 35]);

    $this->actingAs($seller)->get('/verkopen?concept='.$first->id)
        ->assertOk()
        ->assertSee('#'.$first->public_id)
        ->assertSee('Koelcel 20 m3')
        ->assertSee('Rolsteiger 8 meter')
        ->assertSee('35%')
        ->assertSee(route('sell', ['concept' => $second->id]), escape: false)
        ->assertSee(route('sell', ['nieuw' => 1]), escape: false);
});

it('geeft 404 op het concept van iemand anders', function () {
    $other = Lot::factory()->anonymousDraft()->create();

    $this->actingAs(User::factory()->create())->get('/verkopen?concept='.$other->id)->assertNotFound();
});

it('hervat op een ander apparaat via het account, zonder cookie', function () {
    $seller = User::factory()->verified()->create();
    $draft = Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id, 'title' => 'Compressor']);

    $this->actingAs($seller)->get('/verkopen')
        ->assertOk()
        ->assertSee('#'.$draft->public_id);
});

it('begint leeg met ?nieuw=1 en laat de bestaande concepten staan', function () {
    $seller = User::factory()->verified()->create();
    $draft = Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id, 'title' => 'Compressor']);

    $this->actingAs($seller)->get('/verkopen?nieuw=1')
        ->assertOk()
        ->assertDontSee('Je werkt aan concept')
        ->assertSee(route('sell', ['concept' => $draft->id]), escape: false);

    expect(Lot::query()->count())->toBe(1);
});

it('linkt vanuit Mijn kavels naar het juiste concept met de compleetheid', function () {
    $seller = User::factory()->verified()->create();
    $draft = Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id, 'title' => 'Compressor', 'completeness_score' => 48]);

    $this->actingAs($seller)->get(route('dashboard.lots'))
        ->assertOk()
        ->assertSee(route('sell', ['concept' => $draft->id]), escape: false)
        ->assertSee('48%');
});

it('houdt de compleetheidsscore op de rij bij elke telling', function () {
    $this->withCredentials();
    $this->postJson('/api/drafts')->assertCreated();
    $lot = Lot::sole();

    $initial = $lot->completeness_score;

    $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
        ->patchJson("/api/drafts/{$lot->id}", ['title' => 'Een nette titel van genoeg lengte', 'pickup_only' => true])
        ->assertOk();

    expect($lot->fresh()->completeness_score)->toBeGreaterThan($initial);
});

it('toont de flow als losse kaarten, met de bijzaken als pictogram en één rij stappen', function () {
    $user = User::factory()->verified()->create();
    Lot::factory()->anonymousDraft()->create(['seller_id' => $user->id, 'title' => 'Koelcel 20 m3']);
    Lot::factory()->anonymousDraft()->create(['seller_id' => $user->id, 'title' => 'Rolsteiger 8 meter']);

    $this->actingAs($user)->get(route('sell'))
        ->assertOk()
        // De stappen als rij bolletjes in plaats van acht knoppen.
        ->assertSee('class="fn-stepper"', false)
        ->assertDontSee('class="d-step"', false)
        // Elk onderwerp in een eigen kaart, de uitleg achter een vraagteken.
        ->assertSee('class="fn-block', false)
        ->assertSee('class="d-help', false)
        // Bijzaken zijn pictogramknoppen met een naam bij aanwijzen.
        ->assertSee('data-tip="Foto maken"', false)
        ->assertSee('data-tip="Barcode scannen"', false)
        ->assertSee('data-tip="Typeplaat lezen"', false)
        ->assertSee('data-tip="Dicteren"', false)
        ->assertSee('data-tip="Kenmerk toevoegen"', false)
        // De eindcontrole toont wat nog moet; wat in orde is klapt uit.
        ->assertSee('Toon wat in orde is')
        // Stap 1 zegt wat elke kaart doet; stap 3 toont de taken van Ivy op een rij.
        ->assertSee('Hoeveel verkoop je?')
        ->assertSee('Dit is nog niet je advertentie')
        ->assertSee('class="fn-ivytasks"', false)
        ->assertSee('Laat Ivy kijken')
        ->assertDontSee('fn-examples')
        // De andere concepten staan als menu in de kop.
        ->assertSee('class="fn-draftmenu"', false)
        ->assertSee('Nieuw concept')
        ->assertDontSee('Upload foto');
});

it('zegt erbij dat je een bestaand concept hervat, met de weg naar een nieuw', function () {
    $seller = User::factory()->verified()->create();
    Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id, 'title' => 'JBL-speakers']);

    $this->actingAs($seller)->get('/verkopen')
        ->assertOk()
        ->assertSee('Je gaat verder met een concept')
        ->assertSee('Begin een nieuw concept');

    $this->actingAs($seller)->get('/verkopen?nieuw=1')
        ->assertOk()
        ->assertDontSee('Je gaat verder met een concept');
});

it('verwijdert een concept vanaf Mijn kavels en uit het conceptmenu, maar niets anders', function () {
    $seller = User::factory()->verified()->create();
    $draft = Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id, 'title' => 'Oude poging']);
    $kept = Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id, 'title' => 'Blijft']);
    $live = Lot::factory()->live()->create(['seller_id' => $seller->id]);

    // Mijn kavels heeft per concept een prullenbak.
    $this->actingAs($seller)->get(route('dashboard.lots', ['status' => 'concept']))
        ->assertOk()
        ->assertSee(route('dashboard.lots.destroy-draft', $draft), escape: false);

    $this->actingAs($seller)->post(route('dashboard.lots.destroy-draft', $draft))
        ->assertRedirect(route('dashboard.lots', ['status' => 'concept']));

    // Uit de funnel: terug naar het concept waar je was.
    $other = Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id]);
    $this->actingAs($seller)->post(route('dashboard.lots.destroy-draft', $other), ['terug' => 'verkopen', 'concept' => $kept->id])
        ->assertRedirect(route('sell', ['concept' => $kept->id]));

    // Een live kavel of het concept van een ander: 404.
    $this->actingAs($seller)->post(route('dashboard.lots.destroy-draft', $live))->assertNotFound();
    $this->actingAs(User::factory()->verified()->create())->post(route('dashboard.lots.destroy-draft', $kept))->assertNotFound();

    expect(Lot::find($draft->id))->toBeNull()
        ->and(Lot::find($other->id))->toBeNull()
        ->and(Lot::find($kept->id))->not->toBeNull()
        ->and(Lot::find($live->id))->not->toBeNull();
});
