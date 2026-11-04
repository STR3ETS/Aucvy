<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotMedia;

/*
| Wat zoekmachines en deelknoppen nodig hebben: canonical, Open Graph,
| gestructureerde data op de kavel, een sitemap en een robots.txt die
| ernaar verwijst.
*/

function publicLot(): Lot
{
    $lot = Lot::factory()->live()->create([
        'title' => 'Horeca-koelcel 12 m3',
        'category_id' => Category::factory()->create(['name' => 'Horeca & keuken'])->id,
    ]);
    Auction::factory()->create(['lot_id' => $lot->id, 'current_price_cents' => 320_000]);
    LotMedia::factory()->create(['lot_id' => $lot->id, 'path' => 'https://images.example/koelcel.jpg']);

    return $lot;
}

it('zet canonical en open graph op de kavelpagina met de eerste foto', function () {
    $lot = publicLot();

    $this->get(route('lots.show', $lot))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('lots.show', $lot).'">', escape: false)
        ->assertSee('<meta property="og:type" content="product">', escape: false)
        // Elke kavel een eigen deelbeeld (briefing nieuwe functies), met maten erbij.
        ->assertSee('<meta property="og:image" content="'.route('lots.share-image', [$lot, 'landscape']).'">', escape: false)
        ->assertSee('<meta property="og:image:width" content="1200">', escape: false)
        ->assertSee('"@type":"Product"', escape: false)
        ->assertSee('"price":"3200.00"', escape: false)
        ->assertSee('https://schema.org/InStock');
});

it('zet canonical en open graph op elke publieke pagina', function () {
    $this->get('/veilingen')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.url('/veilingen').'">', escape: false)
        ->assertSee('<meta property="og:title" content="Veilingen · Aucvy">', escape: false)
        ->assertSee('<meta property="og:type" content="website">', escape: false);
});

it('levert een sitemap met de publieke kavels en categorieën', function () {
    $lot = publicLot();
    Lot::factory()->anonymousDraft()->create(['title' => 'Nog een concept']);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('lots.show', $lot).'</loc>', escape: false)
        ->assertSee('<loc>'.route('lots.category', $lot->category).'</loc>', escape: false)
        ->assertSee('<loc>'.route('how-it-works').'</loc>', escape: false)
        ->assertDontSee('Nog een concept');
});

it('wijst zoekmachines met robots.txt naar de sitemap en weg van het dashboard', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: '.route('sitemap'))
        ->assertSee('Disallow: /dashboard');
});
