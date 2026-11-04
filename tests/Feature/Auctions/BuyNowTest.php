<?php

declare(strict_types=1);

use App\Auctions\Actions\BuyNow;
use App\Auctions\Actions\PlaceBid;
use App\Auctions\Exceptions\BidRejected;
use App\Auctions\Exceptions\BidRejectionReason;
use App\Enums\AuctionEventType;
use App\Enums\BidStatus;
use App\Enums\BidType;
use App\Enums\LotStatus;
use App\Enums\VatRegime;
use App\Models\Auction;
use App\Models\AuctionEvent;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use App\Support\Money\Money;
use Illuminate\Support\Str;

function buyNowAuction(int $priceCents = 200_000): Auction
{
    $lot = Lot::factory()->live()->create(['vat_regime' => VatRegime::Margin]);

    return Auction::factory()->withBuyNow($priceCents)->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);
}

function buyIt(Auction $auction, User $user): Order
{
    return app(BuyNow::class)->handle($auction, $user, (string) Str::uuid());
}

describe('de bevestigingsstap', function () {
    it('toont de kostenopbouw zonder iets te kopen', function () {
        // Geen schuifknop: eerst tonen, dan pas kopen. Per ongeluk € 13.750
        // uitgeven mag niet één tik kosten (mobiele buildlijst).
        $auction = buyNowAuction();

        $costs = app(BuyNow::class)->preview($auction, transportCents: 34_000);

        expect($costs->hammerCents)->toBe(200_000)
            ->and($costs->transportCents)->toBe(34_000)
            ->and($costs->totalCents)->toBe(Money::forOrder($auction->lot, 200_000, 34_000)->totalCents)
            // Er is niets gebeurd.
            ->and(Order::count())->toBe(0)
            ->and($auction->fresh()->buy_now_active)->toBeTrue();
    });

    it('gebruikt dezelfde berekening als de order die eruit rolt', function () {
        $auction = buyNowAuction();
        $preview = app(BuyNow::class)->preview($auction);

        $order = buyIt($auction, User::factory()->verified()->create());

        expect($order->total_cents)->toBe($preview->totalCents);
    });
});

describe('kopen', function () {
    beforeEach(function () {
        $this->auction = buyNowAuction();
        $this->buyer = User::factory()->verified()->create();
    });

    it('maakt een order aan tegen de vaste prijs', function () {
        $order = buyIt($this->auction, $this->buyer);

        expect($order->hammer_cents)->toBe(200_000)
            ->and($order->buyer_id)->toBe($this->buyer->id)
            ->and($order->total_cents)->toBe(Money::forOrder($this->auction->lot, 200_000)->totalCents);
    });

    it('sluit de veiling meteen', function () {
        buyIt($this->auction, $this->buyer);

        $closed = $this->auction->fresh();

        expect($closed->closed_at)->not->toBeNull()
            ->and($closed->winner_id)->toBe($this->buyer->id)
            ->and($closed->buy_now_active)->toBeFalse()
            ->and($closed->lot->fresh()->status)->toBe(LotStatus::Sold);
    });

    it('markeert het bod als koop-direct', function () {
        buyIt($this->auction, $this->buyer);

        expect(Bid::sole()->type)->toBe(BidType::BuyNow);
    });

    it('zet een staand bod uit de veilingfase op overboden', function () {
        $bidder = User::factory()->verified()->create();
        app(PlaceBid::class)->handle($this->auction, $bidder, 100_000, (string) Str::uuid());

        buyIt($this->auction->fresh(), $this->buyer);

        expect(Bid::where('status', BidStatus::Winning)->count())->toBe(1)
            ->and(Bid::where('user_id', $bidder->id)->sole()->status)
            ->toBe(BidStatus::Outbid);
    });

    it('rekent bij dezelfde sleutel niet twee keer af', function () {
        // Een dubbele klik op "bevestigen" mag geen tweede order opleveren.
        $key = (string) Str::uuid();
        $action = app(BuyNow::class);

        $first = $action->handle($this->auction, $this->buyer, $key);
        $second = $action->handle($this->auction->fresh(), $this->buyer, $key);

        expect($second->id)->toBe($first->id)
            ->and(Order::count())->toBe(1)
            ->and(Bid::count())->toBe(1);
    });

    it('weigert de verkoper', function () {
        $seller = User::factory()->verified()->create();
        $this->auction->lot->update(['seller_id' => $seller->id]);

        expect(fn () => buyIt($this->auction->fresh(), $seller))
            ->toThrow(BidRejected::class, BidRejectionReason::OwnLot->label());
    });

    it('weigert een niet-geverifieerde koper', function () {
        expect(fn () => buyIt($this->auction, User::factory()->create()))
            ->toThrow(BidRejected::class, BidRejectionReason::NotVerified->label());
    });
});

describe('vervallen door een bod', function () {
    it('schakelt koop-direct uit zodra de vaste prijs geboden wordt', function () {
        $auction = buyNowAuction(200_000);

        app(PlaceBid::class)->handle(
            $auction, User::factory()->verified()->create(), 200_000, (string) Str::uuid(),
        );

        expect($auction->fresh()->buy_now_active)->toBeFalse();
    });

    it('laat koop-direct staan zolang het bod eronder blijft', function () {
        $auction = buyNowAuction(200_000);

        app(PlaceBid::class)->handle(
            $auction, User::factory()->verified()->create(), 150_000, (string) Str::uuid(),
        );

        expect($auction->fresh()->buy_now_active)->toBeTrue();
    });

    it('schrijft een eigen event zodat open clients de knop zien verdwijnen', function () {
        /*
         | Dit is een transitie, geen berekening. Zou je het pas bij het klikken
         | afleiden, dan krijgt de koper een fout op het moment dat hij dacht te
         | kopen (hfst. 6).
         */
        $auction = buyNowAuction(200_000);

        app(PlaceBid::class)->handle(
            $auction, User::factory()->verified()->create(), 200_000, (string) Str::uuid(),
        );

        $event = AuctionEvent::where('auction_id', $auction->id)
            ->where('type', AuctionEventType::BuyNowDisabled)
            ->sole();

        expect($event->payload['buy_now_price_cents'])->toBe(200_000)
            ->and($event->payload['reason'])->toBe('outbid');
    });

    it('weigert kopen nadat het vervallen is', function () {
        $auction = buyNowAuction(200_000);

        app(PlaceBid::class)->handle(
            $auction, User::factory()->verified()->create(), 200_000, (string) Str::uuid(),
        );

        expect(fn () => buyIt($auction->fresh(), User::factory()->verified()->create()))
            ->toThrow(BidRejected::class, BidRejectionReason::BuyNowUnavailable->label());
    });

    it('weigert een voorbeeldberekening nadat het vervallen is', function () {
        $auction = buyNowAuction(200_000);
        $auction->update(['buy_now_active' => false]);

        expect(fn () => app(BuyNow::class)->preview($auction->fresh()))
            ->toThrow(BidRejected::class, BidRejectionReason::BuyNowUnavailable->label());
    });

    it('schakelt maar een keer uit', function () {
        // De tweede keer mag er geen tweede event bijkomen.
        $auction = buyNowAuction(200_000);

        app(PlaceBid::class)->handle(
            $auction, User::factory()->verified()->create(), 200_000, (string) Str::uuid(),
        );
        app(PlaceBid::class)->handle(
            $auction->fresh(), User::factory()->verified()->create(), 225_000, (string) Str::uuid(),
        );

        expect(AuctionEvent::where('type', AuctionEventType::BuyNowDisabled)->count())->toBe(1);
    });
});
