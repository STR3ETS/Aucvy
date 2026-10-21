<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Enums\PayoutStatus;
use App\Enums\SaleType;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\Order;
use App\Models\Payout;
use App\Models\User;

/*
| Review van de frontend en de teksten (blok U): wat de pagina belooft moet
| waar zijn, en wat de client aan de server vraagt moet kloppen met wat de
| server teruggeeft.
*/

describe('eerlijke teksten', function () {
    it('belooft geen verificatie van twee minuten op de kavelpagina', function () {
        $lot = Lot::factory()->live()->create();
        Auction::factory()->create(['lot_id' => $lot->id]);

        // Een ingelogde, nog niet geverifieerde kijker krijgt de verwijzing
        // naar de verificatie te zien; die mag geen twee minuten beloven.
        $this->actingAs(User::factory()->create())
            ->get(route('lots.show', $lot))
            ->assertOk()
            ->assertDontSee('dat duurt twee minuten')
            ->assertSee('meestal binnen een werkdag');
    });

    it('toont op de home geen abonnementsprijzen, garanties of verzonnen gebruikers', function (string $url) {
        // De demo in de hero toont fictieve bedragen onder een disclaimer;
        // de tariefkaarten mogen geen maandprijs noemen die niet bestaat.
        $this->get($url)
            ->assertOk()
            ->assertDontSee('Meest gekozen')
            ->assertDontSee('€ 349')
            ->assertDontSee('Zeker winnen')
            ->assertDontSee('Zeker van je zaak')
            ->assertDontSee('Geverifieerde kopers en verkopers')
            ->assertDontSee('Daalt elke 20 seconden')
            ->assertDontSee('Transport direct te boeken')
            ->assertDontSee('Reserveren mogelijk met betaling');
    })->with(['/', '/?variant=a', '/?variant=b', '/?variant=c']);

    it('laat Finn geen verkoopvorm adviseren die niet bestaat', function (string $url) {
        $this->get($url)->assertOk()->assertDontSee('Finn adviseert');
    })->with(['/hoe-het-werkt', '/faq', '/verkooproute']);

    it('noemt een uitbetaling vrijgegeven, niet overgemaakt', function () {
        $this->get('/prijzen')->assertOk()->assertSee('vrijgegeven voor uitbetaling')->assertDontSee('naar je rekening');

        $seller = User::factory()->verified()->create();
        $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Sold]);
        $order = Order::factory()->completed()->create(['seller_id' => $seller->id, 'lot_id' => $lot->id]);
        Payout::forceCreate([
            'order_id' => $order->id, 'seller_id' => $seller->id, 'amount_cents' => 90_000, 'fee_cents' => 7_500,
            'status' => PayoutStatus::Released, 'hold_until' => now()->subDay(),
        ]);

        $this->actingAs($seller)->get('/dashboard/uitbetalingen')
            ->assertOk()
            ->assertSee('Vrijgegeven')
            ->assertDontSee('op je rekening ontvangen');
    });

    it('zet een oude formulierwaarde veilig in de Alpine-staat', function () {
        $this->withSession(['_old_input' => ['type' => "x'; alert(1) //"]])
            ->get('/registreren')
            ->assertOk()
            ->assertDontSee("{ type: 'x'", false);
    });
});

describe('het contract met de client', function () {
    it('geeft de kaart de echte afslaggegevens mee', function () {
        $lot = Lot::factory()->live()->ofType(SaleType::Dutch)->create();
        Auction::factory()->dutch()->create(['lot_id' => $lot->id]);

        $this->get('/veilingen')
            ->assertOk()
            ->assertSee('data-dutch-interval="', false)
            ->assertSee('data-dutch-starts-at="', false)
            ->assertSee('data-dutch-now="', false);
    });

    it('vertelt de kijker zijn eigen id en of de borg te regelen is', function () {
        $user = User::factory()->verified()->create();
        $lot = Lot::factory()->live()->create();
        $auction = Auction::factory()->create(['lot_id' => $lot->id]);

        $this->actingAs($user)->getJson("/api/auctions/{$auction->id}/state")
            ->assertOk()
            ->assertJsonPath('viewer.id', $user->id)
            ->assertJsonPath('viewer.deposit.available', true);

        config(['aucvy.payments.provider' => 'mollie']);

        $this->actingAs($user)->getJson("/api/auctions/{$auction->id}/state")
            ->assertOk()
            ->assertJsonPath('viewer.deposit.available', false);
    });
});
