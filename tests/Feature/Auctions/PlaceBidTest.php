<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceBid;
use App\Auctions\Exceptions\BidRejected;
use App\Auctions\Exceptions\BidRejectionReason;
use App\Enums\AuctionEventType;
use App\Enums\BidStatus;
use App\Enums\BidType;
use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\AuctionEvent;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Deposit;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Support\Str;

function bidder(): User
{
    return User::factory()->verified()->create();
}

function placeBid(Auction $auction, User $user, int $amountCents): Bid
{
    return app(PlaceBid::class)->handle(
        auction: $auction,
        user: $user,
        amountCents: $amountCents,
        idempotencyKey: (string) Str::uuid(),
    );
}

beforeEach(function () {
    $this->auction = Auction::factory()->create([
        'start_price_cents' => 100_000,   // € 1.000
        'current_price_cents' => 100_000,
    ]);
});

describe('een eerste bod', function () {
    it('mag op de startprijs uitkomen', function () {
        // Je hoeft niet bóven de startprijs te beginnen.
        $bid = placeBid($this->auction, bidder(), 100_000);

        expect($bid->amount_cents)->toBe(100_000)
            ->and($bid->status)->toBe(BidStatus::Winning)
            ->and($this->auction->fresh()->current_price_cents)->toBe(100_000);
    });

    it('weigert een bod onder de startprijs', function () {
        expect(fn () => placeBid($this->auction, bidder(), 99_999))
            ->toThrow(BidRejected::class);
    });

    it('werkt de tellers bij in dezelfde transactie', function () {
        placeBid($this->auction, bidder(), 100_000);

        $fresh = $this->auction->fresh();

        expect($fresh->bid_count)->toBe(1)
            ->and($fresh->bidder_count)->toBe(1);
    });
});

describe('de biedstaffel', function () {
    it('vraagt tien euro erbij tot vijfhonderd euro', function () {
        $auction = Auction::factory()->create([
            'start_price_cents' => 40_000,
            'current_price_cents' => 40_000,
        ]);

        placeBid($auction, bidder(), 40_000);

        expect(fn () => placeBid($auction, bidder(), 40_999))->toThrow(BidRejected::class);

        placeBid($auction, bidder(), 41_000);

        expect($auction->fresh()->current_price_cents)->toBe(41_000);
    });

    it('vraagt vijftig euro erbij tussen vijfhonderd en vijfduizend', function () {
        // € 1.000 valt in de middelste band.
        placeBid($this->auction, bidder(), 100_000);

        expect(fn () => placeBid($this->auction, bidder(), 104_999))->toThrow(BidRejected::class);

        placeBid($this->auction, bidder(), 105_000);

        expect($this->auction->fresh()->current_price_cents)->toBe(105_000);
    });

    it('vraagt tweehonderdvijftig euro erbij boven vijfduizend', function () {
        $auction = Auction::factory()->create([
            'start_price_cents' => 600_000,   // € 6.000
            'current_price_cents' => 600_000,
        ]);
        $user = bidder();

        Deposit::factory()->authorised()->create([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
        ]);

        placeBid($auction, $user, 600_000);

        $challenger = bidder();
        Deposit::factory()->authorised()->create([
            'auction_id' => $auction->id,
            'user_id' => $challenger->id,
        ]);

        expect(fn () => placeBid($auction->fresh(), $challenger, 624_999))->toThrow(BidRejected::class);

        placeBid($auction->fresh(), $challenger, 625_000);

        expect($auction->fresh()->current_price_cents)->toBe(625_000);
    });

    it('vertelt bij een te laag bod welk bedrag wel had gemogen', function () {
        placeBid($this->auction, bidder(), 100_000);

        try {
            placeBid($this->auction, bidder(), 100_001);
            $this->fail('Had geweigerd moeten worden.');
        } catch (BidRejected $rejected) {
            // Het biedpaneel moet een bedrag kunnen tonen, geen generieke fout.
            expect($rejected->reason)->toBe(BidRejectionReason::TooLow)
                ->and($rejected->minimumCents)->toBe(105_000)
                ->and($rejected->currentPriceCents)->toBe(100_000);
        }
    });
});

describe('poortwachters', function () {
    it('weigert een niet-geverifieerde bieder', function () {
        $rejected = null;

        try {
            placeBid($this->auction, User::factory()->create(), 100_000);
        } catch (BidRejected $exception) {
            $rejected = $exception;
        }

        // Verificatie zit hard in de motor, niet alleen in de UI (hfst. 13.6).
        expect($rejected?->reason)->toBe(BidRejectionReason::NotVerified)
            ->and($rejected->reason->resolutionRoute())->toBe('account.verification');
    });

    it('weigert een bod op je eigen kavel', function () {
        $seller = User::factory()->verified()->create();
        $lot = Lot::factory()->live()->create(['seller_id' => $seller->id]);
        $auction = Auction::factory()->create(['lot_id' => $lot->id]);

        expect(fn () => placeBid($auction, $seller, 100_000))
            ->toThrow(BidRejected::class, BidRejectionReason::OwnLot->label());
    });

    it('weigert een collega van hetzelfde bedrijf', function () {
        // Shill bidding is een reputatierisico dat je niet achteraf repareert.
        $company = Company::factory()->create();
        $seller = User::factory()->verified()->create(['company_id' => $company->id]);
        $colleague = User::factory()->verified()->create(['company_id' => $company->id]);

        $lot = Lot::factory()->live()->create([
            'seller_id' => $seller->id,
            'company_id' => $company->id,
        ]);
        $auction = Auction::factory()->create(['lot_id' => $lot->id]);

        expect(fn () => placeBid($auction, $colleague, 100_000))
            ->toThrow(BidRejected::class, BidRejectionReason::SameCompany->label());
    });

    it('weigert een bod op een gesloten veiling', function () {
        $auction = Auction::factory()->closed()->create();

        expect(fn () => placeBid($auction, bidder(), 100_000))
            ->toThrow(BidRejected::class);
    });

    it('weigert een bod op een kavel die niet live staat', function () {
        $lot = Lot::factory()->create(['status' => LotStatus::Draft]);
        $auction = Auction::factory()->create(['lot_id' => $lot->id]);

        expect(fn () => placeBid($auction, bidder(), 100_000))
            ->toThrow(BidRejected::class, BidRejectionReason::AuctionNotOpen->label());
    });

    it('weigert tegen jezelf bieden', function () {
        $user = bidder();
        placeBid($this->auction, $user, 100_000);

        expect(fn () => placeBid($this->auction, $user, 200_000))
            ->toThrow(BidRejected::class, BidRejectionReason::AlreadyHighest->label());
    });

    it('vraagt een borg boven de drempel', function () {
        // Voorstel briefing: boven € 2.500 eerst een reservering (hfst. 9).
        $auction = Auction::factory()->create([
            'start_price_cents' => 300_000,
            'current_price_cents' => 300_000,
        ]);

        expect(fn () => placeBid($auction, bidder(), 300_000))
            ->toThrow(BidRejected::class, BidRejectionReason::DepositRequired->label());
    });

    it('laat een bod boven de drempel door met een gereserveerde borg', function () {
        $auction = Auction::factory()->create([
            'start_price_cents' => 300_000,
            'current_price_cents' => 300_000,
        ]);
        $user = bidder();

        Deposit::factory()->authorised()->create([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
        ]);

        expect(placeBid($auction, $user, 300_000)->status)->toBe(BidStatus::Winning);
    });
});

describe('idempotentie', function () {
    it('geeft bij dezelfde sleutel hetzelfde bod terug', function () {
        // Een dubbele submit of een retry na een timeout mag geen tweede bod
        // opleveren (hfst. 6).
        $user = bidder();
        $key = (string) Str::uuid();
        $action = app(PlaceBid::class);

        $first = $action->handle($this->auction, $user, 100_000, $key);
        $second = $action->handle($this->auction, $user, 100_000, $key);

        expect($second->id)->toBe($first->id)
            ->and(Bid::count())->toBe(1)
            ->and($this->auction->fresh()->bid_count)->toBe(1);
    });
});

describe('anti-sniping', function () {
    it('verlengt bij een bod in de slotfase', function () {
        $auction = Auction::factory()->endingIn(5)->create([
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);
        $originalEnd = $auction->ends_at;

        placeBid($auction, bidder(), 100_000);

        $fresh = $auction->fresh();

        expect($fresh->extension_count)->toBe(1)
            ->and($fresh->ends_at->getTimestamp())
            ->toBe($originalEnd->addSeconds(60)->getTimestamp())
            // De oorspronkelijke eindtijd schuift nooit mee.
            ->and($fresh->original_ends_at->getTimestamp())->toBe($originalEnd->getTimestamp());
    });

    it('verlengt niet buiten het slotvenster', function () {
        $auction = Auction::factory()->endingIn(120)->create([
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);

        placeBid($auction, bidder(), 100_000);

        expect($auction->fresh()->extension_count)->toBe(0);
    });

    it('verlengt onbeperkt zolang er telkens opnieuw in de slotfase geboden wordt', function () {
        /*
         | De klok moet hier echt mee vooruit. Na een verlenging ligt de
         | eindtijd 65 seconden weg, dus een direct volgend bod zit búiten het
         | slotvenster en verlengt terecht niet. Alleen door de tijd te
         | verzetten test je wat de briefing bedoelt: "bod op t-5s, herhaald".
         */
        $auction = Auction::factory()->endingIn(5)->create([
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);

        placeBid($auction, bidder(), 100_000);

        $this->travel(60)->seconds();
        placeBid($auction->fresh(), bidder(), 105_000);

        $this->travel(60)->seconds();
        placeBid($auction->fresh(), bidder(), 110_000);

        expect($auction->fresh()->extension_count)->toBe(3)
            // Sluiten gebeurt pas als het echt stil is.
            ->and($auction->fresh()->ends_at->isFuture())->toBeTrue();
    });

    it('verlengt niet als het volgende bod buiten het venster valt', function () {
        // De tegenproef: zonder deze test zou "altijd verlengen" er ook
        // doorheen komen.
        $auction = Auction::factory()->endingIn(5)->create([
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);

        placeBid($auction, bidder(), 100_000);
        placeBid($auction->fresh(), bidder(), 105_000);

        expect($auction->fresh()->extension_count)->toBe(1);
    });

    it('schrijft een verlengevent zodat de klok zichtbaar kan verspringen', function () {
        $auction = Auction::factory()->endingIn(5)->create([
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);

        placeBid($auction, bidder(), 100_000);

        $extended = AuctionEvent::where('auction_id', $auction->id)
            ->where('type', AuctionEventType::Extended)
            ->sole();

        expect($extended->payload)->toHaveKeys(['previous_ends_at', 'ends_at', 'extension_count']);
    });
});

describe('de eventstroom', function () {
    it('geeft elk bod een oplopend volgnummer', function () {
        placeBid($this->auction, bidder(), 100_000);
        placeBid($this->auction->fresh(), bidder(), 125_000);

        expect(Bid::orderBy('sequence')->pluck('sequence')->all())->toBe([1, 2]);
    });

    it('koppelt elk bod aan een event met hetzelfde nummer', function () {
        // Dit is wat een client na een netwerkdip laat terugkomen op de goede
        // plek: "geef me alles na 431".
        placeBid($this->auction, bidder(), 100_000);

        $bid = Bid::sole();
        $event = AuctionEvent::where('auction_id', $this->auction->id)->sole();

        expect($event->sequence)->toBe($bid->sequence)
            ->and($event->payload['amount_cents'])->toBe(100_000);
    });

    it('lekt geen volledige naam in de eventpayload', function () {
        $user = User::factory()->verified()->create([
            'name' => 'Robert Jansen',
            'display_name' => null,
        ]);

        placeBid($this->auction, $user, 100_000);

        $payload = json_encode(AuctionEvent::sole()->payload);

        expect($payload)->toContain('R. Jansen')
            ->not->toContain('Robert Jansen');
    });
});

describe('overbieden', function () {
    it('zet de vorige hoogste bieder op overboden', function () {
        $first = bidder();
        $second = bidder();

        placeBid($this->auction, $first, 100_000);
        placeBid($this->auction->fresh(), $second, 125_000);

        expect(Bid::where('user_id', $first->id)->sole()->status)->toBe(BidStatus::Outbid)
            ->and(Bid::where('user_id', $second->id)->sole()->status)->toBe(BidStatus::Winning);
    });

    it('houdt precies een winnend bod over', function () {
        placeBid($this->auction, bidder(), 100_000);
        placeBid($this->auction->fresh(), bidder(), 125_000);
        placeBid($this->auction->fresh(), bidder(), 150_000);

        expect(Bid::where('status', BidStatus::Winning)->count())->toBe(1);
    });

    it('markeert een handmatig bod als handmatig', function () {
        $bid = placeBid($this->auction, bidder(), 100_000);

        expect($bid->type)->toBe(BidType::Manual)
            ->and($bid->type->isAutomatic())->toBeFalse();
    });
});
