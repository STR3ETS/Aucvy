<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Models\Lot;
use App\Models\Redirect;
use App\Models\User;

/*
| Doorverwijzingen (masterbriefing 7.2) en noindex op gefilterde
| zoekpagina's (7.3).
*/

function redirectStaff(): User
{
    return User::factory()->create(['is_staff' => true]);
}

it('stuurt een oud pad door en telt het gebruik', function () {
    Redirect::create(['from_path' => '/Oude-Pagina/', 'to_url' => '/veilingen', 'status' => 301]);

    $this->get('/oude-pagina')->assertRedirect('/veilingen')->assertStatus(301);
    $this->get('/OUDE-PAGINA/')->assertRedirect('/veilingen');

    $redirect = Redirect::query()->first();

    expect($redirect->from_path)->toBe('/oude-pagina')
        ->and($redirect->hits)->toBe(2)
        ->and($redirect->last_hit_at)->not->toBeNull();
});

it('geeft zonder doorverwijzing gewoon de 404-pagina', function () {
    $this->get('/bestaat-niet')->assertNotFound();
});

it('laat beheer een doorverwijzing aanmaken en verwijderen, met spoor', function () {
    $staff = redirectStaff();

    $this->actingAs($staff)
        ->post(route('admin.redirects.store'), ['from_path' => '/zoekmachine-oud', 'to_url' => 'https://aucvy.nl/veilingen', 'status' => 302])
        ->assertRedirect(route('admin.redirects'));

    $redirect = Redirect::query()->where('from_path', '/zoekmachine-oud')->sole();
    expect($redirect->status)->toBe(302);

    $this->get('/zoekmachine-oud')->assertRedirect('https://aucvy.nl/veilingen')->assertStatus(302);

    $this->actingAs($staff)->get(route('admin.redirects'))->assertOk()->assertSee('/zoekmachine-oud');

    $this->actingAs($staff)->delete(route('admin.redirects.destroy', $redirect))->assertRedirect(route('admin.redirects'));

    expect(Redirect::query()->count())->toBe(0);
    $this->get('/zoekmachine-oud')->assertNotFound();
});

it('weigert een doorverwijzing op een pad dat al een pagina is, en een lus', function () {
    $staff = redirectStaff();

    $this->actingAs($staff)
        ->post(route('admin.redirects.store'), ['from_path' => '/veilingen', 'to_url' => '/categorieen', 'status' => 301])
        ->assertSessionHasErrors('from_path');

    $this->actingAs($staff)
        ->post(route('admin.redirects.store'), ['from_path' => '/oud', 'to_url' => '/oud/', 'status' => 301])
        ->assertSessionHasErrors('to_url');

    $this->actingAs($staff)
        ->post(route('admin.redirects.store'), ['from_path' => 'zonder-slash', 'to_url' => '/veilingen', 'status' => 301])
        ->assertSessionHasErrors('from_path');
});

it('houdt beheer van doorverwijzingen achter de medewerkerspoort', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.redirects'))->assertForbidden();
});

it('zet noindex op gefilterde en gepagineerde zoekpagina\'s, niet op de schone', function () {
    $lot = Lot::factory()->live()->create();
    Auction::factory()->create(['lot_id' => $lot->id]);

    $this->get('/veilingen')->assertOk()->assertDontSee('name="robots"', false);
    $this->get('/veilingen?vorm=dutch')->assertOk()->assertSee('<meta name="robots" content="noindex,follow">', false);
    $this->get('/veilingen?page=2')->assertOk()->assertSee('<meta name="robots" content="noindex,follow">', false);
    $this->get(route('lots.category', $lot->category))->assertOk()->assertDontSee('name="robots"', false);
});
