<?php

declare(strict_types=1);

use App\Support\Blog\SafeHtml;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
| De blog uit het CMS van Eazyonline (besluit Raph, 2 november 2026):
| ophalen met een leestoken, cachen, en de laatst bekende versie tonen als
| de API even weg is. HTML gaat door een allowlist.
*/

beforeEach(function () {
    config([
        'services.eazyonline.base_url' => 'https://eazyonline.test/api/v1',
        'services.eazyonline.token' => 'lees-token',
    ]);
});

function blogItem(array $overrides = []): array
{
    return [
        'id' => 7,
        'type' => 'blog',
        'title' => 'Zo bepaal je de waarde van een heftruck',
        'slug' => 'waarde-heftruck',
        'excerpt' => 'Bouwjaar, uren en onderhoud wegen het zwaarst.',
        'featured_image' => 'https://cdn.eazyonline.test/heftruck.jpg',
        'featured_image_alt' => 'Heftruck in een magazijn',
        'meta_title' => 'Waarde van een heftruck bepalen',
        'meta_description' => 'Waar let een koper op bij een gebruikte heftruck?',
        'published_at' => '2026-10-20T09:00:00+02:00',
        'updated_at' => '2026-10-21T09:00:00+02:00',
        'author' => ['name' => 'Raphael'],
        ...$overrides,
    ];
}

it('toont de berichten uit het CMS met het leestoken', function () {
    Http::fake(['eazyonline.test/*' => Http::response(['data' => [blogItem()], 'meta' => ['current_page' => 1, 'last_page' => 1]])]);

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertSee('Zo bepaal je de waarde van een heftruck')
        ->assertSee(route('blog.show', 'waarde-heftruck'));

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer lees-token')
        && str_contains($request->url(), '/content?')
        && $request['type'] === 'blog');
});

it('toont een bericht met veilige HTML, metadata en gestructureerde data', function () {
    Http::fake(['eazyonline.test/*' => Http::response(['data' => blogItem([
        'content' => '<h2>Uren</h2><p onclick="steel()">Tel de <a href="javascript:alert(1)">uren</a> en <a href="https://voorbeeld.nl">lees meer</a>.</p><script>steel()</script>',
    ])])]);

    $this->get(route('blog.show', 'waarde-heftruck'))
        ->assertOk()
        ->assertSee('<h2>Uren</h2>', escape: false)
        ->assertSee('Waarde van een heftruck bepalen')
        ->assertSee('"@type":"BlogPosting"', escape: false)
        ->assertDontSee('steel()', escape: false)
        ->assertDontSee('javascript:', escape: false)
        ->assertSee('rel="noopener nofollow"', escape: false);
});

it('zet het nieuwste bericht uitgelicht in de kop en de rest in het raster', function () {
    Http::fake(['eazyonline.test/*' => Http::response(['data' => [
        blogItem(),
        blogItem(['id' => 8, 'slug' => 'afslag-of-veiling', 'title' => 'Afslag of veiling?']),
    ], 'meta' => ['current_page' => 1, 'last_page' => 1]])]);

    $html = $this->get(route('blog.index'))
        ->assertOk()
        ->assertSee('Slimmer verkopen,')
        ->assertSee('Uitgelicht')
        ->assertSee('Afslag of veiling?')
        ->assertSee('Staat jouw vraag er niet bij?')
        ->getContent();

    // Het uitgelichte bericht staat één keer op de pagina, niet ook nog in het raster.
    expect(substr_count($html, route('blog.show', 'waarde-heftruck')))->toBe(1);
});

it('maakt van de tussenkoppen een inhoudsopgave', function () {
    Http::fake(['eazyonline.test/*' => Http::response(['data' => blogItem([
        'title' => 'Heftruck verkopen: zo bepaal je de waarde',
        'content' => '<p>Inleiding.</p><h2>Uren</h2><p>Tel ze.</p><h2>Onderhoud &amp; papieren</h2><p>Bewaar ze.</p>',
    ])])]);

    $this->get(route('blog.show', 'waarde-heftruck'))
        ->assertOk()
        ->assertSee('In dit artikel')
        ->assertSee('<a href="#deel-1"><b>1</b>Uren</a>', escape: false)
        ->assertSee('<a href="#deel-2"><b>2</b>Onderhoud &amp; papieren</a>', escape: false)
        ->assertSee('<span class="d-anchor" id="deel-2"></span><h2>Onderhoud &amp; papieren</h2>', escape: false)
        // Het deel na de dubbele punt krijgt het verloop.
        ->assertSee('Heftruck verkopen: <span class="g">zo bepaal je de waarde</span>', escape: false);
});

it('geeft een 404 voor een onbekend bericht', function () {
    Http::fake(['eazyonline.test/*' => Http::response(['message' => 'Not found'], 404)]);

    $this->get(route('blog.show', 'bestaat-niet'))->assertNotFound();
});

it('toont de laatst bekende versie als de API even weg is', function () {
    Http::fake(['eazyonline.test/*' => Http::sequence()
        ->push(['data' => [blogItem()]])
        ->push('fout', 500)]);

    $this->get(route('blog.index'))->assertOk()->assertSee('Zo bepaal je');

    // De verse cache verloopt; de API faalt; de reserve vangt het op.
    cache()->forget('blog.list.1.12');

    $this->get(route('blog.index'))->assertOk()->assertSee('Zo bepaal je');
});

it('toont een nette lege staat zonder token', function () {
    config(['services.eazyonline.token' => null]);
    Http::fake();

    $this->get(route('blog.index'))->assertOk()->assertSee('De blog komt binnenkort online.');

    Http::assertNothingSent();
});

it('zet blogberichten in de sitemap', function () {
    Http::fake(['eazyonline.test/*' => Http::response(['data' => [blogItem()]])]);

    $this->get('/sitemap.xml')->assertOk()->assertSee(route('blog.show', 'waarde-heftruck'), escape: false);
});

it('laat in de HTML alleen opmaak en veilige links over', function () {
    $clean = SafeHtml::clean('<p style="color:red">Hoi <img src="x" onerror="boem()"><img src="https://cdn.test/a.jpg" alt="a"><iframe src="https://evil.test"></iframe><a href="//evil.test">x</a></p>');

    expect($clean)->not->toContain('style=')
        ->not->toContain('onerror')
        ->not->toContain('iframe')
        ->not->toContain('//evil.test')
        ->toContain('src="https://cdn.test/a.jpg"');
});
