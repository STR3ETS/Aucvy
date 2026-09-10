<?php

declare(strict_types=1);

use App\Enums\LotCondition;
use App\Enums\SaleType;
use App\Enums\ValueConfidence;
use App\Lots\LotFilters;
use App\Lots\LotSearch;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Lot;

/**
 * @param  array<string, mixed>  $lotAttributes
 * @param  array<string, mixed>  $auctionAttributes
 */
function listedLot(array $lotAttributes = [], array $auctionAttributes = []): Lot
{
    $lot = Lot::factory()->live()->create($lotAttributes);

    Auction::factory()->create(array_merge([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ], $auctionAttributes));

    return $lot->fresh();
}

function search(LotFilters $filters): LotSearch
{
    return app(LotSearch::class);
}

describe('de pagina', function () {
    it('toont lopende kavels', function () {
        listedLot(['title' => 'Minigraafmachine 3,5 ton']);

        $this->get('/veilingen')
            ->assertOk()
            ->assertSee('Minigraafmachine 3,5 ton');
    });

    it('houdt concepten buiten de lijst', function () {
        Lot::factory()->create(['title' => 'Geheim concept']);

        $this->get('/veilingen')->assertOk()->assertDontSee('Geheim concept');
    });

    it('werkt zonder account', function () {
        listedLot();

        $this->get('/veilingen')->assertOk();
    });
});

describe('filters staan in de url', function () {
    it('leest de verkoopvorm uit de querystring', function () {
        listedLot(['title' => 'Gewone veiling']);
        listedLot(['title' => 'Dalende afslag', 'sale_type' => SaleType::Dutch]);

        $this->get('/veilingen?vorm[]=dutch')
            ->assertOk()
            ->assertSee('Dalende afslag')
            ->assertDontSee('Gewone veiling');
    });

    it('filtert op conditie', function () {
        // Titels die niet toevallig ook in het filterpaneel staan: de labels
        // van álle condities staan daar altijd, ook de uitgevinkte.
        listedLot(['title' => 'Nette bureaustoelen', 'condition' => LotCondition::LikeNew]);
        listedLot(['title' => 'Sloopmateriaal', 'condition' => LotCondition::Defective]);

        $this->get('/veilingen?conditie[]=defective')
            ->assertOk()
            ->assertSee('Sloopmateriaal')
            ->assertDontSee('Nette bureaustoelen');
    });

    it('filtert op prijsbereik in hele euros', function () {
        listedLot(['title' => 'Goedkope kavel'], ['current_price_cents' => 50_000]);
        listedLot(['title' => 'Dure kavel'], ['current_price_cents' => 500_000]);

        // vanaf=1000 betekent € 1.000, dus 100.000 cent.
        $this->get('/veilingen?vanaf=1000')
            ->assertOk()
            ->assertSee('Dure kavel')
            ->assertDontSee('Goedkope kavel');
    });

    it('negeert een verzonnen verkoopvorm in plaats van niets te tonen', function () {
        listedLot(['title' => 'Gewone veiling']);

        $this->get('/veilingen?vorm[]=onzin')->assertOk()->assertSee('Gewone veiling');
    });

    it('houdt de filters vast bij het sorteren', function () {
        $filters = LotFilters::fromRequest(request()->merge([
            'vorm' => ['dutch'],
            'kijkdag' => '1',
        ]));

        expect($filters->toQuery(['sorteer' => 'nieuwste']))
            ->toMatchArray(['vorm' => ['dutch'], 'kijkdag' => 1, 'sorteer' => 'nieuwste']);
    });

    it('laat de standaardsortering uit de url weg', function () {
        // Een schone URL is deelbaarder; de standaard hoeft er niet in.
        expect((new LotFilters)->toQuery())->toBe([]);
    });
});

describe('afslagprijs', function () {
    it('filtert op de gezakte prijs en niet op de opgeslagen kolom', function () {
        /*
         | Bij een afslag loopt de opgeslagen prijs bewust achter. Zou de query
         | die kolom gebruiken, dan valt een afslag van € 20.000 die inmiddels
         | € 8.000 kost buiten een filter "tot € 10.000".
         */
        $lot = Lot::factory()->live()->ofType(SaleType::Dutch)->create(['title' => 'Gezakte afslag']);
        Auction::factory()->dutch(
            startCents: 2_000_000, floorCents: 50_000, stepCents: 25_000, intervalSeconds: 20,
        )->create(['lot_id' => $lot->id, 'starts_at' => now()->subMinutes(20)]);

        /*
         | Na 20 minuten: 60 stappen van € 250 eraf. € 20.000 − € 15.000 =
         | € 5.000, ruim boven de bodem van € 500.
         |
         | De opgeslagen kolom staat nog op € 20.000. Dat "tot € 10.000" hem
         | tóch vindt, bewijst dat de query de gezakte prijs gebruikt.
         */
        $this->get('/veilingen?tot=10000')->assertOk()->assertSee('Gezakte afslag');
        $this->get('/veilingen?vanaf=15000')->assertOk()->assertDontSee('Gezakte afslag');
    });
});

describe('facettellingen', function () {
    it('telt een facet zonder zichzelf te beperken', function () {
        /*
         | Anders zou "afslag (1)" naar 1 zakken zodra je hem aanvinkt en kun
         | je nooit zien wat "veiling" zou opleveren (hfst. 13.2).
         */
        listedLot();
        listedLot();
        listedLot(['sale_type' => SaleType::Dutch]);

        $facets = app(LotSearch::class)->facets(
            LotFilters::fromRequest(request()->merge(['vorm' => ['dutch']])),
        );

        expect($facets['sale_type']['auction'])->toBe(2)
            ->and($facets['sale_type']['dutch'])->toBe(1);
    });

    it('geeft ook de nul-opties terug zodat ze uitgeschakeld getoond kunnen worden', function () {
        listedLot();

        $facets = app(LotSearch::class)->facets(new LotFilters);

        expect($facets['sale_type'])->toHaveKeys(['auction', 'dutch', 'buy_now'])
            ->and($facets['sale_type']['dutch'])->toBe(0);
    });

    it('telt de snelfilters met de overige filters actief', function () {
        listedLot(['sale_type' => SaleType::Dutch], ['ends_at' => now()->addHours(2)]);
        listedLot(['sale_type' => SaleType::Dutch], ['ends_at' => now()->addDays(5)]);

        $facets = app(LotSearch::class)->facets(
            LotFilters::fromRequest(request()->merge(['vorm' => ['dutch']])),
        );

        expect($facets['quick']['alles'])->toBe(2)
            ->and($facets['quick']['laatste-kans'])->toBe(1);
    });
});

describe('de lege staat', function () {
    it('stelt voor welke filter de meeste kavels teruggeeft', function () {
        // Nooit een doodlopende pagina (hfst. 13.2).
        listedLot();
        listedLot();
        listedLot();

        $this->get('/veilingen?vanaf=99999999')
            ->assertOk()
            ->assertSee('Geen kavels gevonden')
            ->assertSee('Zonder het prijsbereik zijn het er 3.')
            ->assertSee('Toon die 3');
    });

    it('kiest de filter die het meeste oplevert', function () {
        listedLot(['condition' => LotCondition::Defective]);
        listedLot(['condition' => LotCondition::Defective]);
        listedLot(['sale_type' => SaleType::Dutch, 'condition' => LotCondition::Good]);

        // Beide filters samen geven nul. Conditie loslaten geeft 1, verkoopvorm
        // loslaten geeft 2 - dus dat laatste is het nuttigste voorstel.
        $suggestion = app(LotSearch::class)->broadeningSuggestion(
            LotFilters::fromRequest(request()->merge([
                'vorm' => ['dutch'],
                'conditie' => ['defective'],
            ])),
        );

        expect($suggestion['count'])->toBe(2)
            ->and($suggestion['filter'])->toBe('saleTypes');
    });

    it('zegt iets zinnigs als er helemaal niets is', function () {
        $this->get('/veilingen')
            ->assertOk()
            ->assertSee('Zet een alert', escape: false);
    });
});

describe('sorteren', function () {
    it('zet standaard de eerst sluitende bovenaan', function () {
        listedLot(['title' => 'Sluit later'], ['ends_at' => now()->addDays(5)]);
        listedLot(['title' => 'Sluit eerst'], ['ends_at' => now()->addHour()]);

        $response = $this->get('/veilingen')->assertOk()->getContent();

        expect(strpos($response, 'Sluit eerst'))->toBeLessThan(strpos($response, 'Sluit later'));
    });

    it('sorteert op prijs', function () {
        listedLot(['title' => 'Duur'], ['current_price_cents' => 900_000]);
        listedLot(['title' => 'Goedkoop'], ['current_price_cents' => 10_000]);

        $response = $this->get('/veilingen?sorteer=prijs-op')->assertOk()->getContent();

        expect(strpos($response, 'Goedkoop'))->toBeLessThan(strpos($response, 'Duur'));
    });

    it('verbergt relevantie zolang er geen zoekterm is', function () {
        listedLot();

        // Op de optiewaarde en niet op de tekst: Blade zet witruimte tussen de
        // tags, dus ">Relevantie<" komt zo nooit letterlijk voor.
        $this->get('/veilingen')->assertOk()->assertDontSee('value="relevantie"', escape: false);
        $this->get('/veilingen?q=iets')->assertOk()->assertSee('value="relevantie"', escape: false);
    });
});

describe('onder marktwaarde', function () {
    it('toont het percentage alleen bij voldoende zekerheid', function () {
        listedLot([
            'title' => 'Scherp geprijsd',
            'value_min_cents' => 200_000,
            'value_max_cents' => 250_000,
            'value_confidence' => ValueConfidence::Medium,
        ], ['current_price_cents' => 100_000]);

        $this->get('/veilingen')->assertOk()->assertSee('50% onder waarde-indicatie');
    });

    it('verbergt het percentage bij lage zekerheid', function () {
        // De indicatie is dan niet scherp genoeg om als koopargument te dienen.
        listedLot([
            'value_min_cents' => 200_000,
            'value_max_cents' => 250_000,
            'value_confidence' => ValueConfidence::Low,
        ], ['current_price_cents' => 100_000]);

        $this->get('/veilingen')->assertOk()->assertDontSee('onder waarde-indicatie');
    });

    it('laat het snelfilter alleen kavels met voldoende zekerheid tonen', function () {
        listedLot([
            'title' => 'Zeker koopje',
            'value_min_cents' => 200_000,
            'value_confidence' => ValueConfidence::High,
        ], ['current_price_cents' => 100_000]);

        listedLot([
            'title' => 'Onzeker koopje',
            'value_min_cents' => 200_000,
            'value_confidence' => ValueConfidence::Low,
        ], ['current_price_cents' => 100_000]);

        $this->get('/veilingen?toon=onder-marktwaarde')
            ->assertOk()
            ->assertSee('Zeker koopje')
            ->assertDontSee('Onzeker koopje');
    });
});

describe('categorieroute', function () {
    it('selecteert de categorie voor en toont ook de subcategorieen', function () {
        $parent = Category::factory()->create(['name' => 'Machines', 'slug' => 'machines']);
        $child = Category::factory()->childOf($parent)->create(['slug' => 'machines-graafmachines']);

        listedLot(['title' => 'Graafmachine', 'category_id' => $child->id]);
        listedLot(['title' => 'Bureaustoel']);

        $this->get('/veilingen/machines')
            ->assertOk()
            ->assertSee('Graafmachine')
            ->assertDontSee('Bureaustoel');
    });
});

describe('zoeken', function () {
    it('zoekt in titel en merk', function () {
        listedLot(['title' => 'Minigraafmachine 3,5 ton', 'brand' => 'Kubota']);
        listedLot(['title' => 'Bureaustoelen', 'brand' => 'Ahrend']);

        $this->get('/veilingen?q=kubota')->assertOk()->assertSee('Minigraafmachine');
        $this->get('/veilingen?q=bureaustoel')->assertOk()->assertDontSee('Minigraafmachine');
    });

    it('behandelt jokertekens als gewone tekst', function () {
        listedLot(['title' => 'Bureaustoelen']);

        // Zonder ontsnapping zou % álles matchen.
        $this->get('/veilingen?q=%25')->assertOk()->assertSee('Geen kavels gevonden');
    });
});

describe('de home', function () {
    it('toont minstens een kavel per verkoopvorm', function () {
        // Anders staat er op een rustige dag zes keer een gewone veiling en
        // lijkt het alsof afslag niet bestaat.
        foreach (range(1, 5) as $i) {
            listedLot(['title' => "Veiling {$i}"]);
        }

        $dutch = Lot::factory()->live()->ofType(SaleType::Dutch)->create(['title' => 'De enige afslag']);
        Auction::factory()->dutch()->create(['lot_id' => $dutch->id, 'starts_at' => now()]);

        $this->get('/')->assertOk()->assertSee('De enige afslag');
    });

    it('zegt het netjes als er niets loopt', function () {
        $this->get('/')->assertOk()->assertSee('Er loopt op dit moment niets');
    });

    it('overleeft een tweede bezoek met een gevulde cache', function () {
        /*
         | Deze test bestaat om een echte fout af te dekken: eerst werden hier
         | Eloquent-modellen gecached. Bij het uitpakken kwam er een
         | __PHP_Incomplete_Class terug in plaats van een model en viel de home
         | om met een 500 - pas zichtbaar bij het tweede bezoek.
         |
         | Nu gaan alleen id's de cache in.
         */
        listedLot(['title' => 'Uitgelichte kavel']);

        $this->get('/')->assertOk()->assertSee('Uitgelichte kavel');

        expect(cache()->has('home.featured.ids'))->toBeTrue();

        $this->get('/')->assertOk()->assertSee('Uitgelichte kavel');
    });

    it('houdt de volgorde uit de cache aan', function () {
        // whereIn geeft geen volgordegarantie; zonder hersorteren staan de
        // kavels na een cachetreffer in databasevolgorde.
        listedLot(['title' => 'Sluit als laatste'], ['ends_at' => now()->addDays(6)]);
        listedLot(['title' => 'Sluit als eerste'], ['ends_at' => now()->addHour()]);

        $this->get('/')->assertOk();
        $html = $this->get('/')->assertOk()->getContent();

        expect(strpos($html, 'Sluit als eerste'))->toBeLessThan(strpos($html, 'Sluit als laatste'));
    });
});
