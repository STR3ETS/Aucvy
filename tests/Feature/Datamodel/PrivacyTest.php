<?php

declare(strict_types=1);

use App\Models\Auction;
use App\Models\AutoBid;
use App\Models\Bid;
use App\Models\User;

/*
| Publieke weergave pseudonimiseert, en een maximumbod is nooit zichtbaar -
| ook niet voor de verkoper. Een maximumbod dat lekt, is een gebruiker die
| geld verliest (hfst. 7 en 15).
*/

it('toont een bieder als initiaal plus achternaam', function () {
    $user = User::factory()->create(['name' => 'Robert Jansen', 'display_name' => null]);

    expect($user->publicName())->toBe('R. Jansen');
});

it('houdt tussenvoegsels heel', function () {
    $user = User::factory()->create(['name' => 'Marieke de Vries', 'display_name' => null]);

    expect($user->publicName())->toBe('M. de Vries');
});

it('gebruikt een zelfgekozen weergavenaam als die er is', function () {
    $user = User::factory()->create([
        'name' => 'Robert Jansen',
        'display_name' => 'Keukenhuis Utrecht',
    ]);

    expect($user->publicName())->toBe('Keukenhuis Utrecht');
});

it('lekt geen naam, e-mailadres of maximumbod in de publieke biedpayload', function () {
    $user = User::factory()->verified()->create([
        'name' => 'Robert Jansen',
        'email' => 'robert@voorbeeld.nl',
    ]);

    $auction = Auction::factory()->create();

    // Deze bieder heeft ook een maximum ingesteld. Dat mag nergens opduiken.
    AutoBid::factory()->for($auction)->for($user)->create(['max_amount_cents' => 2_500_000]);

    $bid = Bid::factory()->for($auction)->for($user)->create(['amount_cents' => 1_285_000]);

    $payload = $bid->toPublicPayload();
    $serialised = json_encode($payload);

    expect($payload)->toHaveKeys(['sequence', 'amount_cents', 'bidder_label', 'type'])
        ->and($payload['bidder_label'])->toBe('R. Jansen')
        ->and($serialised)
        ->not->toContain('robert@voorbeeld.nl')
        ->not->toContain('Robert Jansen')
        // Het maximumbod, in centen én als bedrag.
        ->not->toContain('2500000')
        ->not->toContain('25000.00');
});

it('markeert een automatisch bod als zodanig', function () {
    // De biedhistorie toont "- automatisch" en dat moet kloppen: het is het
    // verschil tussen "iemand bood" en "een ingesteld maximum stapte mee".
    $bid = Bid::factory()->automatic()->create();

    expect($bid->toPublicPayload()['is_automatic'])->toBeTrue();
});
