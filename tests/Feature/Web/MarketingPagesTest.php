<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Lot;

/*
| De drie pagina's achter de hoofdnavigatie: categorieën, hoe het werkt en
| zakelijk. Aantallen, termijnen en tarieven komen uit dezelfde bron als de
| code, en nergens staat een belofte die de code niet waarmaakt.
*/

it('toont het categorie-overzicht met subcategorieën en het aantal zichtbare kavels', function () {
    $root = Category::factory()->create(['name' => 'Horeca & keuken']);
    $child = Category::factory()->create(['name' => 'Koeling & vriescellen', 'parent_id' => $root->id]);
    Lot::factory()->live()->create(['category_id' => $child->id]);
    Lot::factory()->live()->create(['category_id' => $root->id]);
    Lot::factory()->create(['category_id' => $root->id]);

    $this->get('/categorieen')
        ->assertOk()
        ->assertSee('Horeca &amp; keuken', escape: false)
        ->assertSee('Koeling &amp; vriescellen', escape: false)
        ->assertSee('2 kavels')
        ->assertSee(route('lots.category', $child), escape: false);
});

it('legt uit hoe het werkt, met termijnen en tarieven uit dezelfde bron als de code', function () {
    $this->get('/hoe-het-werkt')
        ->assertOk()
        ->assertSee('Van zoeken tot ophalen')
        ->assertSee('binnen '.config('aucvy.orders.payment_deadline_hours').' uur')
        ->assertSee('Van foto tot uitbetaling in zes stappen')
        ->assertSee('7,5%')
        ->assertSee('3,5%')
        ->assertSee('2,5%');
});

it('toont de zakelijke pagina met het team en de tarieven', function () {
    $this->get('/zakelijk')
        ->assertOk()
        ->assertSee('Een verkoopteam dat nooit slaapt.')
        ->assertSee('Lex')
        ->assertSee('Liquidatie')
        ->assertSee('7,5%')
        ->assertSee(route('register'), escape: false);
});

it('claimt nergens escrow of garantie', function (string $url) {
    $this->get($url)->assertOk()->assertDontSee('escrow')->assertDontSee('gegarandeerd');
})->with(['/categorieen', '/hoe-het-werkt', '/zakelijk']);

it('linkt de hoofdnavigatie naar de drie pagina\'s', function () {
    $this->get('/veilingen')
        ->assertOk()
        ->assertSee(route('categories.index'), escape: false)
        ->assertSee(route('how-it-works'), escape: false)
        ->assertSee(route('business'), escape: false);
});
