<?php

declare(strict_types=1);

use App\Auctions\Actions\CloseAuction;
use App\Auctions\Actions\PlaceBid;
use App\Auctions\Events\AuctionClosed;
use App\Auctions\Events\AuctionExtended;
use App\Auctions\Events\BidPlaced;
use App\Auctions\Events\BuyNowDisabled;
use App\Auctions\Events\Outbid;
use App\Models\Auction;
use App\Models\AutoBid;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

function bidAs(Auction $auction, User $user, int $amountCents): void
{
    app(PlaceBid::class)->handle($auction, $user, $amountCents, (string) Str::uuid());
}

beforeEach(function () {
    $this->auction = Auction::factory()->create([
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);
});

describe('uitzenden gebeurt echt', function () {
    it('stuurt een bid.placed na een bod', function () {
        /*
         | Deze test bestaat omdat de events via DB::afterCommit gaan. Zou dat
         | mechanisme in de testomgeving niet vuren, dan zouden alle
         | assertNotDispatched-tests stilzwijgend slagen zonder iets te
         | bewijzen - en zou niemand merken dat er in productie ook niets
         | uitgezonden wordt.
         */
        Event::fake([BidPlaced::class]);

        bidAs($this->auction, User::factory()->verified()->create(), 100_000);

        Event::assertDispatched(BidPlaced::class);
    });
});

describe('kanalen', function () {
    it('zet biedingen op het publieke veilingkanaal', function () {
        Event::fake([BidPlaced::class]);

        bidAs($this->auction, User::factory()->verified()->create(), 100_000);

        Event::assertDispatched(BidPlaced::class, function (BidPlaced $event): bool {
            $channels = array_map(fn ($channel): string => (string) $channel, $event->broadcastOn());

            return $channels === ['auction.'.$this->auction->id]
                && $event->broadcastAs() === 'bid.placed';
        });
    });

    it('zet overboden op het privekanaal van de gebruiker', function () {
        Event::fake([Outbid::class]);

        $first = User::factory()->verified()->create();
        bidAs($this->auction, $first, 100_000);
        bidAs($this->auction->fresh(), User::factory()->verified()->create(), 105_000);

        Event::assertDispatched(Outbid::class, function (Outbid $event) use ($first): bool {
            $channels = array_map(fn ($channel): string => (string) $channel, $event->broadcastOn());

            // Privékanalen dragen het private-voorvoegsel.
            return $channels === ['private-user.'.$first->id]
                && $event->user->id === $first->id;
        });
    });

    it('draait uitzendingen op de realtime-wachtrij', function () {
        // Een uitzending mag nooit achter een AI-taak van veertig seconden in
        // de rij komen te staan (hfst. 3).
        Event::fake([BidPlaced::class]);

        bidAs($this->auction, User::factory()->verified()->create(), 100_000);

        Event::assertDispatched(
            BidPlaced::class,
            fn (BidPlaced $event): bool => $event->broadcastQueue() === 'realtime',
        );
    });
});

describe('payload-hygiene', function () {
    it('lekt geen naam, e-mailadres of maximumbod', function () {
        Event::fake([BidPlaced::class]);

        $user = User::factory()->verified()->create([
            'name' => 'Robert Jansen',
            'email' => 'robert@voorbeeld.nl',
            'display_name' => null,
        ]);

        AutoBid::factory()->for($this->auction)->for($user)->create([
            'max_amount_cents' => 240_000,
        ]);

        bidAs($this->auction, $user, 100_000);

        Event::assertDispatched(BidPlaced::class, function (BidPlaced $event): bool {
            $payload = json_encode($event->broadcastWith());

            return ! str_contains($payload, 'Robert Jansen')
                && ! str_contains($payload, 'robert@voorbeeld.nl')
                && ! str_contains($payload, '240000')
                && str_contains($payload, 'R. Jansen');
        });
    });

    it('stuurt precies de velden uit de briefing mee', function () {
        // Elk extra veld hier is een veld dat je nooit meer terugkrijgt zodra
        // het ooit is uitgezonden.
        Event::fake([BidPlaced::class]);

        bidAs($this->auction, User::factory()->verified()->create(), 100_000);

        Event::assertDispatched(BidPlaced::class, function (BidPlaced $event): bool {
            return array_keys($event->broadcastWith()) === [
                'sequence', 'amount_cents', 'bidder_label', 'type',
                'is_automatic', 'ends_at', 'bid_count', 'bidder_count',
            ];
        });
    });

    it('verzwijgt de winnaar bij het sluiten', function () {
        // Wie gewonnen heeft is een bericht voor de winnaar zelf, niet voor
        // iedereen die toevallig nog op de pagina staat.
        Event::fake([AuctionClosed::class]);

        bidAs($this->auction, User::factory()->verified()->create(), 100_000);
        app(CloseAuction::class)->handle($this->auction->fresh());

        Event::assertDispatched(AuctionClosed::class, function (AuctionClosed $event): bool {
            return ! array_key_exists('winner_id', $event->broadcastWith())
                && $event->broadcastWith()['sold'] === true;
        });
    });
});

describe('overige signalen', function () {
    it('meldt een verlenging met de oude en de nieuwe eindtijd', function () {
        Event::fake([AuctionExtended::class]);

        $auction = Auction::factory()->endingIn(5)->create([
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);

        bidAs($auction, User::factory()->verified()->create(), 100_000);

        Event::assertDispatched(AuctionExtended::class, function (AuctionExtended $event): bool {
            $payload = $event->broadcastWith();

            // De UI laat de klok zichtbaar verspringen; daar heeft hij beide
            // tijden voor nodig.
            return $payload['previous_ends_at'] !== $payload['ends_at']
                && $payload['extension_count'] === 1;
        });
    });

    it('meldt dat koop-direct vervallen is', function () {
        Event::fake([BuyNowDisabled::class]);

        $auction = Auction::factory()->withBuyNow(200_000)->create([
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);

        bidAs($auction, User::factory()->verified()->create(), 200_000);

        Event::assertDispatched(BuyNowDisabled::class, function (BuyNowDisabled $event): bool {
            return $event->broadcastAs() === 'buynow.disabled'
                && $event->broadcastWith()['buy_now_price_cents'] === 200_000;
        });
    });
});
