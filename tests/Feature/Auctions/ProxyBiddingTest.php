<?php

declare(strict_types=1);

use App\Auctions\Actions\CancelAutoBid;
use App\Auctions\Actions\PlaceAutoBid;
use App\Auctions\Actions\PlaceBid;
use App\Auctions\Exceptions\BidRejected;
use App\Auctions\Exceptions\BidRejectionReason;
use App\Enums\BidStatus;
use App\Enums\BidType;
use App\Models\Auction;
use App\Models\AutoBid;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Support\Str;

/*
| Bedragen blijven hier onder de borgdrempel van € 2.500, zodat deze tests over
| proxy-bieden gaan en niet over reserveringen.
*/

function setMax(Auction $auction, User $user, int $maxCents): AutoBid
{
    return app(PlaceAutoBid::class)->handle($auction, $user, $maxCents);
}

function bid(Auction $auction, User $user, int $amountCents): Bid
{
    return app(PlaceBid::class)->handle($auction, $user, $amountCents, (string) Str::uuid());
}

beforeEach(function () {
    $this->auction = Auction::factory()->create([
        'start_price_cents' => 100_000,   // € 1.000
        'current_price_cents' => 100_000,
    ]);

    $this->anna = User::factory()->verified()->create(['name' => 'Anna Bakker']);
    $this->bram = User::factory()->verified()->create(['name' => 'Bram Klaassen']);
});

describe('een maximum instellen', function () {
    it('laat je binnenkomen op de startprijs, niet op je maximum', function () {
        // Dat is het hele punt van een maximum: je betaalt alleen wat nodig is.
        setMax($this->auction, $this->anna, 200_000);

        expect($this->auction->fresh()->current_price_cents)->toBe(100_000);
    });

    it('houdt het maximum verborgen in de biedhistorie', function () {
        setMax($this->auction, $this->anna, 200_000);

        // Er staat een bod van € 1.000, nergens € 2.000.
        expect(Bid::sole()->amount_cents)->toBe(100_000)
            ->and(json_encode(Bid::sole()->toPublicPayload()))->not->toContain('200000');
    });

    it('markeert het als automatisch bod', function () {
        setMax($this->auction, $this->anna, 200_000);

        expect(Bid::sole()->type)->toBe(BidType::Proxy)
            ->and(Bid::sole()->type->isAutomatic())->toBeTrue();
    });
});

describe('maximum tegen handmatig bod', function () {
    it('stapt automatisch mee tot net boven het bod', function () {
        setMax($this->auction, $this->anna, 200_000);

        bid($this->auction->fresh(), $this->bram, 150_000);

        // € 1.500 + één stap van € 50 = € 1.550, ruim onder Anna's plafond.
        expect($this->auction->fresh()->current_price_cents)->toBe(155_000);
    });

    it('laat de uitdager als overboden achter', function () {
        setMax($this->auction, $this->anna, 200_000);

        $bramsBid = bid($this->auction->fresh(), $this->bram, 150_000);

        expect($bramsBid->status)->toBe(BidStatus::Outbid)
            ->and($bramsBid->amount_cents)->toBe(150_000);
    });

    it('schrijft het tegenbod als automatisch', function () {
        setMax($this->auction, $this->anna, 200_000);
        bid($this->auction->fresh(), $this->bram, 150_000);

        $winning = Bid::where('status', BidStatus::Winning)->sole();

        expect($winning->user_id)->toBe($this->anna->id)
            ->and($winning->type)->toBe(BidType::Proxy);
    });

    it('gaat nooit boven het ingestelde plafond', function () {
        setMax($this->auction, $this->anna, 160_000);

        // Bram biedt € 1.580; één stap erbij zou € 1.630 zijn, maar Anna's
        // plafond ligt op € 1.600.
        bid($this->auction->fresh(), $this->bram, 158_000);

        expect($this->auction->fresh()->current_price_cents)->toBe(160_000);
    });

    it('verliest van een bod boven het plafond', function () {
        setMax($this->auction, $this->anna, 200_000);

        $bramsBid = bid($this->auction->fresh(), $this->bram, 220_000);

        // Een handmatig bod is een hard bod: de prijs wordt het geboden bedrag.
        expect($bramsBid->status)->toBe(BidStatus::Winning)
            ->and($this->auction->fresh()->current_price_cents)->toBe(220_000);
    });
});

describe('maximum tegen maximum', function () {
    it('laat het hoogste maximum winnen tegen het tweede plus een stap', function () {
        // Dit is de regel uit de testtabel van hoofdstuk 6.
        setMax($this->auction, $this->anna, 200_000);
        setMax($this->auction->fresh(), $this->bram, 180_000);

        $winning = Bid::where('status', BidStatus::Winning)->sole();

        expect($winning->user_id)->toBe($this->anna->id)
            ->and($this->auction->fresh()->current_price_cents)->toBe(185_000);
    });

    it('laat bij gelijke maxima de vroegste inzending winnen', function () {
        setMax($this->auction, $this->anna, 200_000);
        setMax($this->auction->fresh(), $this->bram, 200_000);

        $winning = Bid::where('status', BidStatus::Winning)->sole();

        expect($winning->user_id)->toBe($this->anna->id)
            ->and($this->auction->fresh()->current_price_cents)->toBe(200_000);
    });

    it('lost het in een keer op, zonder heen en weer te stappen', function () {
        // Naïef doorrekenen levert een oneindige lus of een reeks nepbiedingen
        // op. Twee maxima horen precies twee rijen te geven (hfst. 6).
        setMax($this->auction, $this->anna, 200_000);
        setMax($this->auction->fresh(), $this->bram, 180_000);

        expect(Bid::count())->toBe(3); // Anna's opening, Bram's poging, Anna's antwoord
    });
});

describe('je eigen maximum beheren', function () {
    it('verhoogt zonder je eigen prijs op te drijven', function () {
        setMax($this->auction, $this->anna, 150_000);
        $priceBefore = $this->auction->fresh()->current_price_cents;

        setMax($this->auction->fresh(), $this->anna, 240_000);

        expect($this->auction->fresh()->current_price_cents)->toBe($priceBefore)
            ->and(AutoBid::active()->sole()->max_amount_cents)->toBe(240_000)
            // Geen extra biedrij: er is niets gebeurd wat het publiek aangaat.
            ->and(Bid::count())->toBe(1);
    });

    it('weigert een verlaging', function () {
        setMax($this->auction, $this->anna, 200_000);

        expect(fn () => setMax($this->auction->fresh(), $this->anna, 150_000))
            ->toThrow(BidRejected::class, BidRejectionReason::AutoBidNotIncreasing->label());
    });

    it('weigert hetzelfde bedrag nog een keer', function () {
        setMax($this->auction, $this->anna, 200_000);

        expect(fn () => setMax($this->auction->fresh(), $this->anna, 200_000))
            ->toThrow(BidRejected::class);
    });

    it('houdt bij intrekken het reeds geplaatste bod overeind', function () {
        // De UI moet dit ook zeggen: je trekt je plafond in, niet je bod.
        setMax($this->auction, $this->anna, 200_000);

        app(CancelAutoBid::class)->handle($this->auction, $this->anna);

        expect(AutoBid::active()->count())->toBe(0)
            ->and(Bid::where('status', BidStatus::Winning)->sole()->user_id)->toBe($this->anna->id)
            ->and($this->auction->fresh()->current_price_cents)->toBe(100_000);
    });

    it('stapt niet meer mee na intrekken', function () {
        setMax($this->auction, $this->anna, 200_000);
        app(CancelAutoBid::class)->handle($this->auction, $this->anna);

        bid($this->auction->fresh(), $this->bram, 150_000);

        expect(Bid::where('status', BidStatus::Winning)->sole()->user_id)->toBe($this->bram->id)
            ->and($this->auction->fresh()->current_price_cents)->toBe(150_000);
    });

    it('laat een nieuw maximum toe na een intrekking', function () {
        // De gegenereerde active_flag geeft de unieke index weer vrij.
        setMax($this->auction, $this->anna, 200_000);
        app(CancelAutoBid::class)->handle($this->auction, $this->anna);
        bid($this->auction->fresh(), $this->bram, 150_000);

        setMax($this->auction->fresh(), $this->anna, 240_000);

        expect(AutoBid::where('user_id', $this->anna->id)->count())->toBe(2)
            ->and(AutoBid::active()->where('user_id', $this->anna->id)->count())->toBe(1);
    });
});

describe('poortwachters gelden ook voor maxima', function () {
    it('weigert een maximum van een niet-geverifieerde gebruiker', function () {
        $unverified = User::factory()->create();

        expect(fn () => setMax($this->auction, $unverified, 200_000))
            ->toThrow(BidRejected::class, BidRejectionReason::NotVerified->label());
    });

    it('weigert een maximum onder het biedminimum', function () {
        bid($this->auction, $this->anna, 100_000);

        expect(fn () => setMax($this->auction->fresh(), $this->bram, 100_000))
            ->toThrow(BidRejected::class, BidRejectionReason::TooLow->label());
    });

    it('vraagt een borg over het maximum, niet over de huidige prijs', function () {
        // Je verplicht je tot het plafond, dus daar hoort de borg bij te passen.
        expect(fn () => setMax($this->auction, $this->anna, 300_000))
            ->toThrow(BidRejected::class, BidRejectionReason::DepositRequired->label());
    });
});
