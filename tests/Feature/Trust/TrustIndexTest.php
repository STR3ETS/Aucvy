<?php

declare(strict_types=1);

use App\Models\Lot;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Support\Trust\TrustIndex;

/*
| De Trust Index (preview v4, briefing hfst. 19): één cijfer uit gemeten
| signalen. De eerlijkheidsregels zijn het belangrijkst: onder de tien
| transacties geen score, en alleen gemeten factoren tellen mee.
*/

function sellerWithHistory(int $orders, int $rating = 5): User
{
    $seller = User::factory()->verified()->create();
    $buyer = User::factory()->verified()->create();

    foreach (range(1, $orders) as $index) {
        $lot = Lot::factory()->live()->create(['seller_id' => $seller->id]);

        $order = Order::factory()->completed()->create([
            'lot_id' => $lot->id,
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'paid_at' => now()->subDays(10),
            'shipped_at' => now()->subDays(8),
        ]);

        Review::factory()->create([
            'order_id' => $order->id,
            'reviewer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'company_id' => $seller->company_id,
            'rating' => $rating,
            'published_at' => now()->subDays(7),
        ]);
    }

    return $seller;
}

it('toont bewust niets onder de tien transacties', function () {
    $seller = sellerWithHistory(9);

    expect(app(TrustIndex::class)->forSeller($seller))->toBeNull();
});

it('berekent een score uit de gemeten factoren', function () {
    $seller = sellerWithHistory(10);

    $trust = app(TrustIndex::class)->forSeller($seller);

    // Perfecte cijfers behalve documenten (geen enkele kavel heeft er een,
    // dus die factor scoort terecht 0 en drukt het totaal).
    expect($trust)->not->toBeNull()
        ->and($trust['score'])->toBeGreaterThanOrEqual(70)
        ->and($trust['score'])->toBeLessThan(90)
        ->and($trust['transactions'])->toBe(10)
        ->and(collect($trust['factors'])->firstWhere('key', 'documents')['score'])->toBe(0)
        ->and(array_column($trust['factors'], 'key'))
        ->toContain('transactions', 'satisfaction', 'settlement', 'delivery');
});

it('weegt slechte reviews zichtbaar mee', function () {
    $good = app(TrustIndex::class)->forSeller(sellerWithHistory(10, 5));
    $poor = app(TrustIndex::class)->forSeller(sellerWithHistory(10, 2));

    expect($poor['score'])->toBeLessThan($good['score']);
});

it('staat op het verkopersprofiel zodra hij bestaat, met de disclaimers', function () {
    $seller = sellerWithHistory(10);

    $this->get('/verkoper/'.$seller->id)
        ->assertOk()
        ->assertSee('Trust Index')
        ->assertSee('geen garantie')
        ->assertSee('Koperstevredenheid');
});

it('ontbreekt op het profiel onder de drempel', function () {
    $seller = sellerWithHistory(3);

    $this->get('/verkoper/'.$seller->id)
        ->assertOk()
        ->assertDontSee('Trust Index');
});
