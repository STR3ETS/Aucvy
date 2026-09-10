<?php

declare(strict_types=1);

use App\Auctions\Actions\CloseAuction;
use App\Enums\DepositStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Deposit;
use App\Models\User;

/*
| "Vrijgeven gebeurt automatisch bij verliezen, en bij winnen wordt de borg
| verrekend met de order" (hfst. 9). Het verliezerspad zit in CloseAuction,
| het winnaarspad in TransitionOrder - zie OrderTransitionTest.
*/

it('geeft de borg van verliezers direct terug bij het sluiten', function () {
    $auction = Auction::factory()->endingIn(-60)->create();

    $winner = User::factory()->verified()->create();
    $loser = User::factory()->verified()->create();

    Bid::factory()->create([
        'auction_id' => $auction->id,
        'user_id' => $winner->id,
        'sequence' => 1,
    ]);

    foreach ([$winner, $loser] as $bidder) {
        Deposit::create([
            'user_id' => $bidder->id,
            'auction_id' => $auction->id,
            'amount_cents' => 250_000,
            'status' => DepositStatus::Authorised,
        ]);
    }

    app(CloseAuction::class)->handle($auction);

    // De verliezer krijgt zijn borg per direct terug; die van de winnaar
    // blijft staan tot de order betaald is.
    expect(Deposit::where('user_id', $loser->id)->sole()->status)->toBe(DepositStatus::Released)
        ->and(Deposit::where('user_id', $winner->id)->sole()->status)->toBe(DepositStatus::Authorised);
});

it('geeft bij een onverkochte veiling iedereen zijn borg terug', function () {
    // Geen winnend bod: geen order, en dus ook geen borg die blijft hangen.
    $auction = Auction::factory()->endingIn(-60)->create();
    $bidder = User::factory()->verified()->create();

    Deposit::create([
        'user_id' => $bidder->id,
        'auction_id' => $auction->id,
        'amount_cents' => 250_000,
        'status' => DepositStatus::Authorised,
    ]);

    app(CloseAuction::class)->handle($auction);

    expect(Deposit::sole()->status)->toBe(DepositStatus::Released);
});
