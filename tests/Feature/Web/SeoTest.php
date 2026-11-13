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
        ->assertSee('<meta property="og:title" content="Veilingen: machines, inventaris en bedrijfsgoederen · Aucvy">', escape: false)
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

it('geeft elke vaste pagina een eigen zoektitel en een beschrijving van hooguit 155 tekens', function (string $route) {
    $html = $this->get(route($route))->assertOk()->getContent();

    preg_match('/<meta name="description" content="([^"]*)"/', $html, $description);
    preg_match('/<title>([^<]*)<\/title>/', $html, $title);

    expect($description[1] ?? '')->not->toBe('')
        ->and(mb_strlen(html_entity_decode($description[1] ?? '')))->toBeLessThanOrEqual(155)
        ->and(mb_strlen(html_entity_decode($title[1] ?? '')))->toBeLessThanOrEqual(60);
})->with(['home', 'lots.index', 'lots.buy-now', 'categories.index', 'how-it-works', 'selling.guide', 'pricing', 'business', 'assurance', 'faq', 'contact', 'sell']);

it('beschrijft de site, de veelgestelde vragen en het kruimelpad voor zoekmachines', function () {
    $lot = Lot::factory()->live()->create();

    $this->get(route('home'))->assertOk()
        ->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"SearchAction"', false);

    $this->get(route('faq'))->assertOk()
        ->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@type":"Question"', false);

    $this->get(route('lots.show', $lot))->assertOk()
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"item":"'.route('lots.show', $lot).'"', false);
});

it('zet verkopersprofielen in de sitemap en houdt account en privé deellinks buiten de index', function () {
    $lot = Lot::factory()->live()->create();
    Cache::forget('sitemap.xml');

    $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.route('sellers.show', $lot->seller).'</loc>', false);
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /account')->assertSee('Disallow: /kavel/*/d/');
});
