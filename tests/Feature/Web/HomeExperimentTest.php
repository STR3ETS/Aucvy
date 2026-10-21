<?php

declare(strict_types=1);

use App\Enums\HomeVariant;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Lot;
use App\Support\Experiments\HomeExperiment;
use Illuminate\Support\Lottery;

/*
| De A/B-test op de home: wie welk ontwerp ziet en hoe lang dat zo blijft.
| De toewijzing zelf is een loterij; de tests dwingen de uitkomst af, want
| een test die "meestal" slaagt bewijst niets.
*/

afterEach(fn () => Lottery::determineResultsNormally());

function homeLot(string $title): Lot
{
    $lot = Lot::factory()->live()->create(['title' => $title]);

    Auction::factory()->create([
        'lot_id' => $lot->id,
        'current_price_cents' => 940_000,
    ]);

    return $lot;
}

describe('zonder lopende test', function () {
    it('toont iedereen het gekozen ontwerp: variant c', function () {
        $this->get('/')
            ->assertOk()
            ->assertViewIs('home-c')
            ->assertCookieMissing(HomeExperiment::COOKIE);
    });

    it('negeert een toewijzing uit de testperiode die nog in de cookie staat', function () {
        $this->withCookie(HomeExperiment::COOKIE, 'a')
            ->get('/')
            ->assertOk()
            ->assertViewIs('home-c');
    });

    it('laat het oude ontwerp bekijken via de url, zonder cookie', function () {
        $this->get('/?variant=a')
            ->assertOk()
            ->assertViewIs('home')
            ->assertCookieMissing(HomeExperiment::COOKIE);
    });

    it('negeert een variant die niet bestaat', function () {
        $this->get('/?variant=z')
            ->assertOk()
            ->assertViewIs('home-c');
    });

    it('laat de hybride variant bekijken via de url', function () {
        $this->get('/?variant=c')
            ->assertOk()
            ->assertViewIs('home-c')
            ->assertCookieMissing(HomeExperiment::COOKIE);
    });

    it('volgt het standaardontwerp uit de config', function () {
        config()->set('aucvy.experiments.home.default', 'a');

        $this->get('/')->assertOk()->assertViewIs('home');
    });
});

describe('de toewijzing', function () {
    beforeEach(fn () => config()->set('aucvy.experiments.home.enabled', true));

    it('wijst het nieuwe ontwerp toe en zet de cookie', function () {
        Lottery::alwaysWin();

        $this->get('/')
            ->assertOk()
            ->assertViewIs('home-v4')
            ->assertCookie(HomeExperiment::COOKIE, 'b');
    });

    it('wijst het huidige ontwerp toe en zet de cookie', function () {
        Lottery::alwaysLose();

        $this->get('/')
            ->assertOk()
            ->assertViewIs('home')
            ->assertCookie(HomeExperiment::COOKIE, 'a');
    });

    it('houdt zich aan een eerdere toewijzing in de cookie', function () {
        Lottery::alwaysWin();

        $this->withCookie(HomeExperiment::COOKIE, 'a')
            ->get('/')
            ->assertOk()
            ->assertViewIs('home');
    });

    it('laat een url-keuze boven de cookie gaan en overschrijft hem', function () {
        $this->withCookie(HomeExperiment::COOKIE, 'a')
            ->get('/?variant=b')
            ->assertOk()
            ->assertViewIs('home-v4')
            ->assertCookie(HomeExperiment::COOKIE, 'b');
    });

    it('onthoudt een url-keuze ook zonder eerdere cookie', function () {
        $this->get('/?variant=a')
            ->assertOk()
            ->assertViewIs('home')
            ->assertCookie(HomeExperiment::COOKIE, 'a');
    });

    it('zet in demo-stand geen cookie, zodat de vergelijkingspagina niets verandert', function () {
        Lottery::alwaysLose();

        $this->get('/?variant=b&demo=1')
            ->assertOk()
            ->assertViewIs('home-v4')
            ->assertCookieMissing(HomeExperiment::COOKIE)
            ->assertDontSee('A/B-test: ontwerp wisselen');
    });
});

describe('het nieuwe ontwerp', function () {
    it('houdt dezelfde toegankelijkheidsvloer als de publieke layout', function () {
        $this->get('/?variant=b')
            ->assertOk()
            ->assertSee('lang="nl"', escape: false)
            ->assertSee('Naar de hoofdinhoud')
            ->assertSee('id="hoofdinhoud"', escape: false);
    });

    it('toont dezelfde live kavels en categorieën als het oude ontwerp', function () {
        homeLot('Complete designkeuken met Siemens-apparatuur');
        Category::factory()->create(['name' => 'Horeca & keuken']);

        $this->get('/?variant=b')
            ->assertOk()
            ->assertSee('Complete designkeuken met Siemens-apparatuur')
            ->assertSee('Horeca &amp; keuken', escape: false);
    });

    it('haalt de tarieven uit dezelfde bron als de code', function () {
        $this->get('/?variant=b')
            ->assertOk()
            ->assertSee('7,5% bij verkoop')
            ->assertSee('3,5% bij verkoop')
            ->assertSee('2,5% bij verkoop');
    });

    it('claimt nergens escrow of garantie', function () {
        $this->get('/?variant=b')->assertDontSee('escrow')->assertDontSee('gegarandeerd');
    });

    it('biedt de schakelaar naar het andere ontwerp', function () {
        $this->get('/?variant=b')
            ->assertSee('A/B-test: ontwerp wisselen')
            ->assertSee(route('home', ['variant' => HomeVariant::Current->value]), escape: false);
    });
});

describe('de hybride variant', function () {
    it('is de opbouw van A in de stijl van de stijlgids', function () {
        homeLot('Complete designkeuken met Siemens-apparatuur');

        $this->get('/?variant=c')
            ->assertOk()
            ->assertSee('class="no-js c"', escape: false)
            // De kop van A, met Ivy als gradientwoord.
            ->assertSee('<span class="g">Ivy</span>', escape: false)
            ->assertSee('Van foto tot uitbetaling in zes stappen')
            ->assertSee('Complete designkeuken met Siemens-apparatuur')
            // Het team zweeft rechts in de hero.
            ->assertSee('hero-team')
            // Geen A/B-schakelaar op C; wisselen kan via /ab-test.
            ->assertDontSee('A/B-test: ontwerp wisselen');
    });

    it('claimt nergens escrow of garantie', function () {
        $this->get('/?variant=c')->assertDontSee('escrow')->assertDontSee('gegarandeerd');
    });
});

describe('de vergelijkingspagina', function () {
    it('laadt alle ontwerpen in demo-stand', function () {
        $this->get('/ab-test')
            ->assertOk()
            ->assertSee('variant=a&amp;demo=1', escape: false)
            ->assertSee('variant=b&amp;demo=1', escape: false)
            ->assertSee('variant=c&amp;demo=1', escape: false)
            ->assertSee(HomeVariant::Hybrid->label());
    });
});
