<?php

declare(strict_types=1);

/*
| De foutpagina's in de huisstijl: een code, een titel in gewone taal en de
| weg terug. Geen kale Laravel-pagina's.
*/

it('toont een eigen 404-pagina in de siteschil, met de weg terug', function () {
    $lot = App\Models\Lot::factory()->live()->create(['title' => 'Kolomboor nog te koop']);
    App\Models\Auction::factory()->create(['lot_id' => $lot->id]);

    $this->get('/deze-pagina-bestaat-niet')
        ->assertNotFound()
        ->assertSee('Pagina niet gevonden')
        ->assertSee('onder de hamer geweest.')
        ->assertSee('Naar de veilingen')
        // Lopende kavels als weg terug, en niet in de zoekmachine.
        ->assertSee('Kolomboor nog te koop')
        ->assertSee('<meta name="robots" content="noindex,follow">', escape: false);
});

it('geeft api-aanroepen gewoon json terug', function () {
    $this->getJson('/api/drafts/999999')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
