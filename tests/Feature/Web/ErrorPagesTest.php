<?php

declare(strict_types=1);

/*
| De foutpagina's in de huisstijl: een code, een titel in gewone taal en de
| weg terug. Geen kale Laravel-pagina's.
*/

it('toont een eigen 404-pagina met de weg terug', function () {
    $this->get('/deze-pagina-bestaat-niet')
        ->assertNotFound()
        ->assertSee('Pagina niet gevonden')
        ->assertSee('Naar de veilingen')
        ->assertSee('class="no-js c auth"', escape: false);
});

it('geeft api-aanroepen gewoon json terug', function () {
    $this->getJson('/api/drafts/999999')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
