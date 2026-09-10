<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Models\AutoBid;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;

/*
| Deze tests bewaken de databasegaranties waar de biedmotor in stap 3 op gaat
| leunen. Ze horen bij het datamodel en niet bij de motor: als een van deze
| sleutels wegvalt, faalt de motor pas onder gelijktijdige belasting - en dan
| ben je hem in productie kwijt in plaats van in CI.
*/

it('weigert twee biedingen met dezelfde idempotency key', function () {
    // Een dubbele submit of een retry na een timeout mag geen tweede bod
    // opleveren (hfst. 6).
    $auction = Auction::factory()->create();

    Bid::factory()->for($auction)->create([
        'idempotency_key' => 'zelfde-sleutel',
        'sequence' => 1,
    ]);

    expect(fn () => Bid::factory()->for($auction)->create([
        'idempotency_key' => 'zelfde-sleutel',
        'sequence' => 2,
    ]))->toThrow(QueryException::class);
});

it('weigert twee biedingen op dezelfde plek in de volgorde', function () {
    $auction = Auction::factory()->create();

    Bid::factory()->for($auction)->create(['sequence' => 7]);

    expect(fn () => Bid::factory()->for($auction)->create(['sequence' => 7]))
        ->toThrow(QueryException::class);
});

it('staat dezelfde volgorde wel toe op een andere veiling', function () {
    // De teller loopt per veiling, niet globaal.
    Bid::factory()->for(Auction::factory()->create())->create(['sequence' => 1]);
    Bid::factory()->for(Auction::factory()->create())->create(['sequence' => 1]);

    expect(Bid::where('sequence', 1)->count())->toBe(2);
});

describe('automatisch bieden', function () {
    it('staat maar een actief maximum toe per bieder per veiling', function () {
        $auction = Auction::factory()->create();
        $user = User::factory()->verified()->create();

        AutoBid::factory()->for($auction)->for($user)->create(['max_amount_cents' => 500_000]);

        expect(fn () => AutoBid::factory()->for($auction)->for($user)->create([
            'max_amount_cents' => 900_000,
        ]))->toThrow(QueryException::class);
    });

    it('staat wel meerdere ingetrokken maxima toe naast een actief maximum', function () {
        // Dit is precies waarom active_flag NULL wordt zodra een maximum
        // ingetrokken is: NULL-waarden botsen niet in een MySQL unique index,
        // dus de historie blijft bewaard.
        $auction = Auction::factory()->create();
        $user = User::factory()->verified()->create();

        AutoBid::factory()->for($auction)->for($user)->cancelled()->create();
        AutoBid::factory()->for($auction)->for($user)->cancelled()->create();
        AutoBid::factory()->for($auction)->for($user)->create();

        expect(AutoBid::where('auction_id', $auction->id)->count())->toBe(3)
            ->and(AutoBid::active()->where('auction_id', $auction->id)->count())->toBe(1);
    });

    it('staat een actief maximum toe voor elke bieder afzonderlijk', function () {
        $auction = Auction::factory()->create();

        AutoBid::factory()->for($auction)->create();
        AutoBid::factory()->for($auction)->create();

        expect(AutoBid::active()->where('auction_id', $auction->id)->count())->toBe(2);
    });
});

it('koppelt een veiling aan precies een kavel', function () {
    $auction = Auction::factory()->create();

    expect(fn () => Auction::factory()->create(['lot_id' => $auction->lot_id]))
        ->toThrow(QueryException::class);
});

it('bewaart de oorspronkelijke eindtijd los van de verschoven eindtijd', function () {
    // ends_at schuift bij verlenging, original_ends_at nooit - nodig voor
    // rapportage en voor "3x verlengd" (hfst. 5).
    $auction = Auction::factory()->create();
    $original = $auction->original_ends_at;

    // Rechtstreeks toewijzen, niet via update(): de tellers op `auctions`
    // staan bewust niet in #[Fillable], zodat alleen de biedmotor ze kan
    // schrijven en nooit een controller met een request-payload.
    $auction->ends_at = $auction->ends_at->addSeconds(60);
    $auction->extension_count++;
    $auction->save();

    $fresh = $auction->fresh();

    expect($fresh->original_ends_at->toDateTimeString())->toBe($original->toDateTimeString())
        ->and($fresh->ends_at->greaterThan($fresh->original_ends_at))->toBeTrue()
        ->and($fresh->wasExtended())->toBeTrue();
});

it('gooit een fout als iemand een motor-teller via mass assignment probeert te zetten', function () {
    // Dankzij Model::shouldBeStrict() is dit een luide fout in plaats van een
    // stilzwijgend genegeerd veld. Zie AppServiceProvider.
    $auction = Auction::factory()->create();

    expect(fn () => $auction->update(['extension_count' => 99]))
        ->toThrow(MassAssignmentException::class);
});
