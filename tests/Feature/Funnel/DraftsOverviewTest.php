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
        // De andere concepten staan als menu in de kop.
        ->assertSee('class="fn-draftmenu"', false)
        ->assertSee('Nieuw concept')
        ->assertDontSee('Upload foto');
});
