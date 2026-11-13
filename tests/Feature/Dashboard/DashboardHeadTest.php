<?php

declare(strict_types=1);

use App\Models\Lot;
use App\Models\User;

/*
| De kop van elke dashboardpagina: een eigen titel, een beschrijving, het
| icoon van Aucvy en geen plek in een zoekindex.
*/

it('geeft elke dashboardpagina een titel, een beschrijving, het icoon en noindex', function (string $route, string $title) {
    $user = User::factory()->verified()->business()->create();

    $this->actingAs($user)->get(route($route))
        ->assertOk()
        ->assertSee('<title>'.$title.' · '.config('app.name').'</title>', false)
        ->assertSee('<meta name="description" content="', false)
        ->assertSee('<link rel="icon" href="'.asset('favicon.svg').'" type="image/svg+xml">', false)
        ->assertSee('<link rel="apple-touch-icon"', false)
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
})->with([
    ['dashboard.lots', 'Mijn kavels'],
    ['dashboard.bids', 'Mijn biedingen'],
    ['dashboard.watchlist', 'Volglijst'],
    ['dashboard.finance', 'Financiën'],
    ['ivy.inbox', 'Je Ivy-overzicht'],
]);

it('zegt in de titel van een kaveldossier dat het een dossier is', function () {
    $lot = Lot::factory()->live()->create(['title' => 'Heftruck Linde H25']);

    $this->actingAs($lot->seller)->get(route('dashboard.lots.show', $lot))
        ->assertOk()
        ->assertSee('<title>Dossier: Heftruck Linde H25 · '.config('app.name').'</title>', false)
        ->assertSee('Het dossier van kavel #'.$lot->public_id, false);
});

it('heeft een echt icoon in plaats van een leeg favicon.ico', function () {
    expect(filesize(public_path('favicon.ico')))->toBeGreaterThan(100)
        ->and(file_exists(public_path('favicon.svg')))->toBeTrue()
        ->and(file_exists(public_path('apple-touch-icon.png')))->toBeTrue()
        ->and(json_decode((string) file_get_contents(public_path('site.webmanifest')), true)['name'])->toBe('Aucvy');
});
