<?php

declare(strict_types=1);

use App\Auctions\Actions\AcceptDutchPrice;
use App\Auctions\Actions\PlaceBid;
use App\Auctions\Exceptions\BidRejected;
use App\Auctions\Exceptions\BidRejectionReason;
use App\Auctions\Support\DutchPriceCalculator;
use App\Enums\BidType;
use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Deposit;
use App\Models\Order;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Str;

/*
| Afslag: € 2.000 startprijs, elke 20 seconden € 250 eraf, bodem € 500.
*/

function dutchAuction(): Auction
{
    return Auction::factory()
        ->dutch(startCents: 200_000, floorCents: 50_000, stepCents: 25_000, intervalSeconds: 20)
        ->create(['starts_at' => now()]);
}

function accept(Auction $auction, User $user, int $seenPriceCents)
{
    return app(AcceptDutchPrice::class)->handle(
        auction: $auction,
        user: $user,
        seenPriceCents: $seenPriceCents,
        idempotencyKey: (string) Str::uuid(),
    );
}

describe('de prijs als functie van de tijd', function () {
    beforeEach(function () {
        $this->auction = dutchAuction();
        $this->calculator = app(DutchPriceCalculator::class);
    });

    it('begint op de startprijs', function () {
        expect($this->calculator->priceAt($this->auction))->toBe(200_000);
    });

    it('zakt pas na een vol interval', function () {
        $this->travel(19)->seconds();
        expect($this->calculator->priceAt($this->auction))->toBe(200_000);

        $this->travel(1)->seconds();
        expect($this->calculator->priceAt($this->auction))->toBe(175_000);
    });

    it('zakt met vaste stappen', function () {
        $this->travel(60)->seconds();

        // Drie intervallen van € 250 eraf.
        expect($this->calculator->priceAt($this->auction))->toBe(125_000);
    });

    it('zakt nooit onder de bodem', function () {
        // Ruim voorbij het punt waarop de bodem bereikt is.
        $this->travel(1)->hour();

        expect($this->calculator->priceAt($this->auction))->toBe(50_000)
            ->and($this->calculator->hasReachedFloor($this->auction))->toBeTrue();
    });

    it('schrijft niets weg om de prijs bij te houden', function () {
        // Geen tikkende schrijfacties: de kolom blijft op de startprijs staan
        // tot er daadwerkelijk iets gebeurt (hfst. 6).
        $this->travel(60)->seconds();
        $this->calculator->priceAt($this->auction);

        expect($this->auction->fresh()->current_price_cents)->toBe(200_000);
    });

    it('vertelt wanneer de prijs weer zakt', function () {
        $this->travel(5)->seconds();

        $nextDrop = $this->calculator->nextDropAt($this->auction);

        expect($nextDrop->getTimestamp())
            ->toBe($this->auction->starts_at->addSeconds(20)->getTimestamp());
    });

    it('stopt met aftellen zodra de bodem bereikt is', function () {
        // Een teller die nergens heen loopt is erger dan geen teller.
        $this->travel(1)->hour();

        expect($this->calculator->nextDropAt($this->auction))->toBeNull()
            ->and($this->calculator->stateFor($this->auction)['floor_reached'])->toBeTrue();
    });
});

describe('afmijnen', function () {
    beforeEach(function () {
        $this->auction = dutchAuction();
        $this->buyer = User::factory()->verified()->create();

        Deposit::factory()->authorised()->create([
            'auction_id' => $this->auction->id,
            'user_id' => $this->buyer->id,
        ]);
    });

    it('rekent af tegen de prijs die de server berekent', function () {
        $this->travel(40)->seconds();   // € 2.000 − 2 × € 250 = € 1.500

        $order = accept($this->auction, $this->buyer, 150_000);

        expect($order->hammer_cents)->toBe(150_000)
            ->and($order->buyer_id)->toBe($this->buyer->id);
    });

    it('sluit de veiling meteen', function () {
        $order = accept($this->auction, $this->buyer, 200_000);

        $closed = $this->auction->fresh();

        expect($closed->closed_at)->not->toBeNull()
            ->and($closed->winner_id)->toBe($this->buyer->id)
            ->and($closed->lot->fresh()->status)->toBe(LotStatus::Sold)
            ->and($order)->not->toBeNull();
    });

    it('markeert het bod als afgemijnd', function () {
        accept($this->auction, $this->buyer, 200_000);

        expect(Bid::sole()->type)->toBe(BidType::Dutch);
    });

    it('rekent het ordertotaal met dezelfde klasse als de kavelpagina', function () {
        $order = accept($this->auction, $this->buyer, 200_000);

        expect($order->total_cents)
            ->toBe(Money::forOrder($this->auction->lot, 200_000)->totalCents);
    });

    it('laat een stap speling toe tussen scherm en server', function () {
        // Tussen het renderen van de knop en het aankomen van het verzoek kan
        // de prijs één keer gezakt zijn; daar mag een koper niet op stuklopen.
        $this->travel(20)->seconds();   // server: € 1.750

        $order = accept($this->auction, $this->buyer, 200_000);   // scherm: € 2.000

        // Er wordt afgerekend tegen wat de server berekent, niet tegen het scherm.
        expect($order->hammer_cents)->toBe(175_000);
    });

    it('weigert een scherm dat te ver achterloopt', function () {
        $this->travel(60)->seconds();   // server: € 1.250

        try {
            accept($this->auction, $this->buyer, 200_000);
            $this->fail('Had geweigerd moeten worden.');
        } catch (BidRejected $rejected) {
            // De actuele prijs gaat mee terug, zodat de UI direct de nieuwe
            // knop kan tonen.
            expect($rejected->reason)->toBe(BidRejectionReason::PriceMoved)
                ->and($rejected->currentPriceCents)->toBe(125_000);
        }
    });

    it('laat de eerste klik winnen', function () {
        $second = User::factory()->verified()->create();
        Deposit::factory()->authorised()->create([
            'auction_id' => $this->auction->id,
            'user_id' => $second->id,
        ]);

        accept($this->auction, $this->buyer, 200_000);

        // De tweede klik landt op een gesloten veiling.
        expect(fn () => accept($this->auction->fresh(), $second, 200_000))
            ->toThrow(BidRejected::class);

        expect(Order::count())->toBe(1)
            ->and($this->auction->fresh()->winner_id)->toBe($this->buyer->id);
    });

    it('rekent bij dezelfde sleutel niet twee keer af', function () {
        // Een retry na een timeout mag geen tweede aankoop opleveren.
        $key = (string) Str::uuid();
        $action = app(AcceptDutchPrice::class);

        $first = $action->handle($this->auction, $this->buyer, 200_000, $key);
        $second = $action->handle($this->auction->fresh(), $this->buyer, 200_000, $key);

        expect($second->id)->toBe($first->id)
            ->and(Order::count())->toBe(1)
            ->and(Bid::count())->toBe(1);
    });

    it('weigert de verkoper', function () {
        $seller = User::factory()->verified()->create();
        $this->auction->lot->update(['seller_id' => $seller->id]);

        expect(fn () => accept($this->auction->fresh(), $seller, 200_000))
            ->toThrow(BidRejected::class, BidRejectionReason::OwnLot->label());
    });

    it('weigert een niet-geverifieerde koper', function () {
        expect(fn () => accept($this->auction, User::factory()->create(), 200_000))
            ->toThrow(BidRejected::class, BidRejectionReason::NotVerified->label());
    });
});

describe('verkeerde verkoopvorm', function () {
    it('laat niet bieden op een afslag', function () {
        $auction = dutchAuction();

        expect(fn () => app(PlaceBid::class)->handle(
            $auction, User::factory()->verified()->create(), 200_000, (string) Str::uuid(),
        ))->toThrow(BidRejected::class, BidRejectionReason::SaleTypeMismatch->label());
    });

    it('laat niet afmijnen op een gewone veiling', function () {
        $auction = Auction::factory()->create();

        expect(fn () => accept($auction, User::factory()->verified()->create(), 100_000))
            ->toThrow(BidRejected::class, BidRejectionReason::SaleTypeMismatch->label());
    });
});
