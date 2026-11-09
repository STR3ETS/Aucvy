<?php

declare(strict_types=1);

use App\Auctions\Actions\CloseAuction;
use App\Auctions\Actions\PlaceBid;
use App\Enums\KycStatus;
use App\Models\Auction;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\AuctionLost;
use App\Notifications\OrderCreated;
use App\Notifications\OutbidMail;
use App\Notifications\OutbidPush;
use App\Notifications\VerificationDecided;
use App\Notifications\Welcome;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/*
| De mails uit de briefing (sectie 8) die een gebeurtenis in de code
| afdwingt: welkom bij registratie, overboden zonder push, verloren na de
| hamerslag en de uitkomst van de verificatie.
*/

beforeEach(fn () => Notification::fake());

it('stuurt een welkomstmail na registratie', function () {
    $this->post('/registreren', [
        'terms' => '1',
        'name' => 'Sanne de Boer',
        'email' => 'sanne@example.test',
        'password' => 'wachtwoord-1234',
        'password_confirmation' => 'wachtwoord-1234',
        'type' => 'private',
    ])->assertRedirect();

    $user = User::query()->where('email', 'sanne@example.test')->sole();

    Notification::assertSentTo($user, Welcome::class);
});

it('mailt een overboden bieder zonder ingeschreven apparaat, en pusht wie er wel een heeft', function () {
    $lot = Lot::factory()->live()->create();
    $auction = Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);
    $anna = User::factory()->verified()->create();
    $bram = User::factory()->verified()->create();
    $bram->updatePushSubscription('https://push.example/bram', 'p256dh-sleutel', 'auth-token', 'aes128gcm');

    app(PlaceBid::class)->handle($auction, $anna, 110_000, (string) Str::uuid());
    app(PlaceBid::class)->handle($auction, $bram, 120_000, (string) Str::uuid());
    app(PlaceBid::class)->handle($auction, $anna, 130_000, (string) Str::uuid());

    Notification::assertSentTo($anna, OutbidMail::class, fn (OutbidMail $mail) => $mail->event->newPriceCents === 120_000);
    Notification::assertNotSentTo($anna, OutbidPush::class);
    Notification::assertSentTo($bram, OutbidPush::class);
    Notification::assertNotSentTo($bram, OutbidMail::class);
});

it('mailt de bieders die niet wonnen na de hamerslag, en de winnaar niet', function () {
    $lot = Lot::factory()->live()->create();
    $auction = Auction::factory()->create([
        'lot_id' => $lot->id,
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
        'ends_at' => now()->addMinute(),
    ]);
    $anna = User::factory()->verified()->create();
    $bram = User::factory()->verified()->create();

    app(PlaceBid::class)->handle($auction, $anna, 110_000, (string) Str::uuid());
    app(PlaceBid::class)->handle($auction, $bram, 120_000, (string) Str::uuid());

    $this->travel(2)->minutes();
    app(CloseAuction::class)->handle($auction->fresh());

    Notification::assertSentTo($anna, AuctionLost::class, fn (AuctionLost $mail) => $mail->auction->id === $auction->id);
    Notification::assertNotSentTo($bram, AuctionLost::class);
    Notification::assertSentTo($bram, OrderCreated::class);
});

it('mailt de uitkomst van de identiteitscontrole pas na het besluit', function () {
    $user = User::factory()->create(['kyc_status' => KycStatus::Unverified]);

    $this->actingAs($user)->post('/verificatie/identiteit')->assertRedirect();

    Notification::assertNotSentTo($user, VerificationDecided::class);

    $staff = User::factory()->create(['is_staff' => true]);
    $this->actingAs($staff)->post(route('admin.compliance.kyc', $user), ['decision' => 'approve'])->assertRedirect();

    Notification::assertSentTo($user, VerificationDecided::class, fn (VerificationDecided $mail) => $mail->status === KycStatus::Verified);
});
