<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceAutoBid;
use App\Auctions\Actions\PlaceBid;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Enums\ValueConfidence;
use App\Models\Auction;
use App\Models\Deposit;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->lot = Lot::factory()->live()->create([
        'title' => 'Complete designkeuken met Siemens-apparatuur',
        'public_id' => '8842',
        'slug' => 'complete-designkeuken-8842',
    ]);

    $this->auction = Auction::factory()->withReserve(1_200_000)->create([
        'lot_id' => $this->lot->id,
        'start_price_cents' => 800_000,
        'current_price_cents' => 800_000,
    ]);
});

function lotUrl(Lot $lot): string
{
    return '/kavel/'.$lot->slug;
}

/**
 * Haalt de veilingstand uit de x-data van de pagina.
 *
 * Js::from() rendert `JSON.parse('…')` met alle aanhalingstekens als ",
 * zodat het veilig in een HTML-attribuut past. Zoeken op rauwe JSON werkt dus
 * niet; decoderen is bovendien leesbaarder én strenger dan tekst zoeken.
 *
 * @return array<string, mixed>
 */
function embeddedState(string $html): array
{
    preg_match("/x-data=\"auction\(JSON\.parse\('(.*?)'\)\)\"/s", $html, $matches);

    if (! isset($matches[1])) {
        return [];
    }

    /*
     | Twee stappen, net als de browser:
     |
     |   1. de JS-stringliteral uitpakken - daar zitten de "'s die de
     |      aanhalingstekens van de JSON zélf voorstellen;
     |   2. de JSON die daaruit komt decoderen.
     |
     | Stap 1 doen we door de tekst als JSON-string te decoderen; die kent
     | dezelfde \uXXXX-notatie.
     */
    $json = json_decode('"'.$matches[1].'"');

    return json_decode((string) $json, true) ?? [];
}

describe('zichtbaarheid', function () {
    it('toont een lopende kavel', function () {
        $this->get(lotUrl($this->lot))
            ->assertOk()
            ->assertSee('Complete designkeuken met Siemens-apparatuur');
    });

    it('verbergt een concept, ook via een gedeelde url', function () {
        // Een concept is nooit publiek (hfst. 13.4).
        $draft = Lot::factory()->create(['status' => LotStatus::Draft]);

        $this->get(lotUrl($draft))->assertNotFound();
    });

    it('werkt zonder account', function () {
        $this->get(lotUrl($this->lot))
            ->assertOk()
            ->assertSee('Inloggen om te bieden');
    });
});

describe('eerste render', function () {
    it('bevat de volledige stand zodat de pagina klopt zonder websocket', function () {
        // Belangrijk voor SEO en voor de eerste seconde op mobiel (hfst. 13.3).
        $this->get(lotUrl($this->lot))
            ->assertOk()
            ->assertSee('x-data="auction(', escape: false)
            ->assertSee('minimum_bid_cents', escape: false)
            ->assertSee('anti_snipe_window_seconds', escape: false);
    });

    it('toont de kostenopbouw uit de money-klasse', function () {
        $this->get(lotUrl($this->lot))
            ->assertOk()
            ->assertSee('Kosten vóór je biedt')
            ->assertSee('Margeregeling - btw niet verrekenbaar');
    });
});

describe('niets mag uitlekken', function () {
    it('noemt de reserveprijs nergens', function () {
        // Niet de reserveprijs is publiek, wel of hij gehaald is (hfst. 5).
        $response = $this->get(lotUrl($this->lot))->assertOk();

        expect($response->getContent())->not->toContain('1200000');
    });

    it('toont bieders als initiaal plus achternaam', function () {
        $bidder = User::factory()->verified()->create([
            'name' => 'Robert Jansen',
            'display_name' => null,
        ]);

        // € 8.000 ligt boven de borgdrempel, dus de motor eist een reservering.
        Deposit::factory()->authorised()->create([
            'auction_id' => $this->auction->id,
            'user_id' => $bidder->id,
        ]);

        app(PlaceBid::class)->handle($this->auction, $bidder, 800_000, (string) Str::uuid());

        $response = $this->get(lotUrl($this->lot))->assertOk();

        expect($response->getContent())
            ->toContain($bidder->publicName())
            ->not->toContain('Robert Jansen');
    });

    it('lekt geen maximumbod van een ander', function () {
        $anna = User::factory()->verified()->create();
        Deposit::factory()->authorised()->create([
            'auction_id' => $this->auction->id,
            'user_id' => $anna->id,
        ]);

        app(PlaceAutoBid::class)->handle($this->auction, $anna, 2_400_000);

        // Bram kijkt mee.
        $this->actingAs(User::factory()->verified()->create());

        $response = $this->get(lotUrl($this->lot->fresh()))->assertOk();

        expect($response->getContent())->not->toContain('2400000');
    });

    it('toont je eigen maximum wel aan jezelf', function () {
        $anna = User::factory()->verified()->create();
        Deposit::factory()->authorised()->create([
            'auction_id' => $this->auction->id,
            'user_id' => $anna->id,
        ]);

        app(PlaceAutoBid::class)->handle($this->auction, $anna, 2_400_000);

        $this->actingAs($anna);

        expect($this->get(lotUrl($this->lot->fresh()))->getContent())->toContain('2400000');
    });
});

describe('de kijker', function () {
    it('meldt aan de verkoper dat het zijn eigen kavel is', function () {
        $this->actingAs($this->lot->seller);

        $state = embeddedState($this->get(lotUrl($this->lot))->assertOk()->getContent());

        expect($state['viewer']['is_seller'])->toBeTrue();
    });

    it('meldt aan een niet-geverifieerde gebruiker dat verificatie nodig is', function () {
        $this->actingAs(User::factory()->create());

        $response = $this->get(lotUrl($this->lot))->assertOk();

        expect(embeddedState($response->getContent())['viewer']['can_bid'])->toBeFalse();

        $response->assertSee('Rond eerst je verificatie af');
    });
});

describe('verkoopvormen', function () {
    it('toont bij een afslag het afslagpaneel en geen biedveld', function () {
        // Eén verkoopvorm tegelijk; drie panelen naast elkaar is keuzestress
        // op 390px (hfst. 12).
        $lot = Lot::factory()->live()->ofType(SaleType::Dutch)->create(['slug' => 'koelcel-7734']);
        Auction::factory()->dutch()->create(['lot_id' => $lot->id, 'starts_at' => now()]);

        $this->get(lotUrl($lot))
            ->assertOk()
            ->assertSee('Huidige prijs')
            ->assertSee('Inloggen om af te mijnen')
            ->assertDontSee('Inloggen om te bieden');
    });

    it('toont koop-direct naast de veiling', function () {
        $auction = Auction::factory()->withBuyNow(78_000)->create([
            'lot_id' => Lot::factory()->live()->create(['slug' => 'bureaustoelen-6203'])->id,
            'start_price_cents' => 40_000,
            'current_price_cents' => 40_000,
        ]);

        $response = $this->get(lotUrl($auction->lot))->assertOk()->assertSee('Koop direct');

        expect(embeddedState($response->getContent())['buy_now'])
            ->toMatchArray(['active' => true, 'price_cents' => 78_000]);
    });
});

describe('waarde-indicatie', function () {
    it('waarschuwt bij lage zekerheid in plaats van een scherp bedrag te suggereren', function () {
        $lot = Lot::factory()->live()
            ->withValueEstimate(confidence: ValueConfidence::Low)
            ->create(['slug' => 'onzeker-1234']);
        Auction::factory()->create(['lot_id' => $lot->id]);

        $this->get(lotUrl($lot))
            ->assertOk()
            ->assertSee('Lage zekerheid')
            ->assertSee('een bezichtiging is hier verstandig', escape: false);
    });

    it('toont niets als de range onvolledig is', function () {
        $lot = Lot::factory()->live()->create([
            'slug' => 'geenrange-1235',
            'value_min_cents' => 100_000,
            'value_max_cents' => null,
        ]);
        Auction::factory()->create(['lot_id' => $lot->id]);

        $this->get(lotUrl($lot))->assertOk()->assertDontSee('Ivy-indicatie');
    });
});
