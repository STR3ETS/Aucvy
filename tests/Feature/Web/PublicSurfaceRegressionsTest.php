<?php

declare(strict_types=1);

use App\Auctions\Actions\CloseAuction;
use App\Auctions\Actions\PlaceBid;
use App\Enums\LotStatus;
use App\Enums\OrderStatus;
use App\Enums\ViewingStatus;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Viewing;
use App\Models\WatchlistItem;
use App\Notifications\ViewingConfirmed;
use App\Notifications\ViewingRequested;
use App\Support\Markdown;
use App\Support\Privacy\AnonymiseUser;
use App\Support\Privacy\ExportUserData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/*
| Review van de publieke kant (blok R): wat een bezoeker, een volger, een
| doorgestuurde mail of een opsombaar id níét mag opleveren.
*/

describe('de kavelpagina', function () {
    it('laat een beschrijving het JSON-LD-script niet sluiten', function () {
        $lot = Lot::factory()->live()->create([
            'description' => '</script><script>alert(1)</script> nette machine, weinig draaiuren',
        ]);
        Auction::factory()->create(['lot_id' => $lot->id]);

        $html = $this->get(route('lots.show', $lot))->assertOk()->getContent();

        expect($html)
            ->not->toContain('</script><script>alert(1)')
            ->toContain('</script>');
    });
});

describe('de volglijst', function () {
    it('weigert een concept van iemand anders', function () {
        $user = User::factory()->create();
        $draft = Lot::factory()->create(['status' => LotStatus::Draft]);

        $this->actingAs($user)->postJson("/api/lots/{$draft->id}/watch")->assertNotFound();

        expect(WatchlistItem::query()->count())->toBe(0);
    });

    it('volgt een publieke kavel gewoon', function () {
        $user = User::factory()->create();
        $lot = Lot::factory()->live()->create();

        $this->actingAs($user)->postJson("/api/lots/{$lot->id}/watch")->assertCreated();
    });

    it('verklapt bij ontvolgen geen titel van een kavel die nooit gevolgd werd', function () {
        $user = User::factory()->create();
        $draft = Lot::factory()->create(['status' => LotStatus::Draft, 'title' => 'Geheime partij koper']);

        $this->actingAs($user)
            ->from(route('dashboard.watchlist'))
            ->delete(route('dashboard.watchlist.remove', $draft))
            ->assertRedirect()
            ->assertSessionHas('status', fn (string $status): bool => ! str_contains($status, 'Geheime partij koper'));
    });

    it('toont een gepauzeerde veiling niet als lopend', function () {
        $user = User::factory()->create();
        $lot = Lot::factory()->create(['status' => LotStatus::Paused, 'title' => 'Shovel Volvo L60', 'published_at' => now()->subDay()]);
        Auction::factory()->create(['lot_id' => $lot->id, 'paused_at' => now(), 'ends_at' => now()->addMinutes(30)]);
        WatchlistItem::query()->create(['user_id' => $user->id, 'lot_id' => $lot->id]);

        $this->actingAs($user)->get(route('dashboard.watchlist'))
            ->assertOk()
            ->assertSee('gepauzeerd')
            ->assertDontSee('Sluit bijna');
    });
});

describe('de veilingstand', function () {
    it('meldt een gepauzeerde veiling als gepauzeerd, zonder minimumbod', function () {
        $lot = Lot::factory()->create(['status' => LotStatus::Paused, 'published_at' => now()->subDay()]);
        $auction = Auction::factory()->create(['lot_id' => $lot->id, 'paused_at' => now()]);

        $this->getJson("/api/auctions/{$auction->id}/state")
            ->assertOk()
            ->assertJsonPath('status', 'paused')
            ->assertJsonPath('minimum_bid_cents', null)
            ->assertJsonPath('quick_bid_cents', null);
    });

    it('geeft het ordernummer van de winnaar niet mee in de replay', function () {
        $auction = Auction::factory()->create(['start_price_cents' => 100_000, 'current_price_cents' => 100_000]);
        app(PlaceBid::class)->handle($auction, User::factory()->verified()->create(), 100_000, (string) Str::uuid());

        $order = app(CloseAuction::class)->handle($auction->fresh());

        $events = $this->getJson("/api/auctions/{$auction->id}/events?after=0")->assertOk()->json('events');
        $closed = collect($events)->firstWhere('type', 'closed');

        expect($order)->not->toBeNull()
            ->and($closed['payload'])->toHaveKey('sold')
            ->and($closed['payload'])->not->toHaveKey('order_number')
            ->and(json_encode($events))->not->toContain($order->order_number);
    });

    it('telt per adres hooguit vijf tabbladen mee', function () {
        $lot = Lot::factory()->live()->create();
        $auction = Auction::factory()->create(['lot_id' => $lot->id]);

        $last = null;
        for ($i = 0; $i < 8; $i++) {
            $last = $this->postJson("/api/auctions/{$auction->id}/presence", ['token' => "tab-{$i}-1234567890"]);
        }

        $last->assertOk()->assertJsonPath('watching_now', 5);
    });
});

describe('het verkopersprofiel', function () {
    it('bestaat niet voor een account dat nooit iets aangeboden heeft', function () {
        $buyer = User::factory()->create();

        $this->get(route('sellers.show', $buyer))->assertNotFound();
    });

    it('bestaat voor wie een kavel aanbiedt', function () {
        $lot = Lot::factory()->live()->create();
        Auction::factory()->create(['lot_id' => $lot->id]);

        $this->get(route('sellers.show', $lot->seller))->assertOk();
    });
});

describe('push-inschrijvingen', function () {
    it('weigert een endpoint dat geen https-pushdienst is', function (string $endpoint) {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/push/subscription', ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'x', 'auth' => 'y']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('endpoint');
    })->with([
        'http' => 'http://push.example/apparaat',
        'intern ip' => 'https://10.0.0.5:8080/',
        'localhost' => 'https://localhost/push',
        'ipv6' => 'https://[::1]/push',
        'zonder punt' => 'https://intranet/push',
    ]);

    it('accepteert een echte pushdienst', function () {
        $this->actingAs(User::factory()->create())
            ->postJson('/api/push/subscription', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'x', 'auth' => 'y']])
            ->assertCreated();
    });
});

describe('bezichtigingslinks', function () {
    beforeEach(fn () => Notification::fake());

    function requestedViewing(Lot $lot, ?string $note = null): Viewing
    {
        return Viewing::create([
            'lot_id' => $lot->id,
            'user_id' => User::factory()->verified()->create()->id,
            'slot_at' => now()->addDays(2),
            'status' => ViewingStatus::Requested,
            'note' => $note,
        ]);
    }

    it('verlopen na twee weken', function () {
        $lot = Lot::factory()->live()->create(['viewing_available' => true]);
        $mail = (new ViewingRequested(requestedViewing($lot)))->toMail($lot->seller);

        expect($mail->actionUrl)->toContain('expires=');

        $this->travel(ViewingRequested::LINK_VALID_DAYS + 1)->days();

        $this->get($mail->actionUrl)->assertForbidden();
    });

    it('bevestigen niet meer als de kavel voorbij is', function () {
        $lot = Lot::factory()->create(['status' => LotStatus::Sold, 'published_at' => now()->subDays(3)]);
        $viewing = requestedViewing($lot);

        $this->get(URL::signedRoute('viewings.confirm', ['viewing' => $viewing]))->assertOk();

        expect($viewing->fresh()->status)->toBe(ViewingStatus::Requested)
            ->and($viewing->fresh()->address_sent_at)->toBeNull();
        Notification::assertNotSentTo($viewing->user, ViewingConfirmed::class);
    });

    it('maken van een notitie geen klikbare link in de mail', function () {
        $lot = Lot::factory()->live()->create(['viewing_available' => true]);
        $viewing = requestedViewing($lot, '[Bevestig de bezichtiging hier](https://nep.example/login)');

        $html = (string) (new ViewingRequested($viewing))->toMail($lot->seller)->render();

        expect($html)
            ->not->toContain('href="https://nep.example/login"')
            ->toContain('[Bevestig de bezichtiging hier]');
    });
});

describe('vrije tekst in mails', function () {
    it('ontsnapt Markdown maar laat URL\'s heel', function () {
        expect(Markdown::escape('[klik](https://x) *nu* # kop'))->toBe('\[klik\](https://x) \*nu\* \# kop')
            ->and(Markdown::escape("- punt\n1. een"))->toBe("\\- punt\n1\\. een")
            ->and(Markdown::escapeValues(['lot' => 'a_b', 'url' => 'https://a_b', 'count' => 3]))
            ->toBe(['lot' => 'a\_b', 'url' => 'https://a_b', 'count' => 3]);
    });
});

describe('anonimisering', function () {
    it('kan ook voor wie ooit verkocht heeft', function () {
        $seller = User::factory()->verified()->create();
        $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Sold, 'published_at' => now()->subDays(10)]);
        Order::factory()->completed()->create(['seller_id' => $seller->id, 'lot_id' => $lot->id]);

        app(AnonymiseUser::class)->handle($seller);

        expect(DB::table('users')->where('id', $seller->id)->value('name'))->toBe('Verwijderde gebruiker');
    });

    it('weigert bij een gepauzeerde kavel', function () {
        $seller = User::factory()->verified()->create();
        Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Paused]);

        app(AnonymiseUser::class)->handle($seller);
    })->throws(RuntimeException::class);

    it('ruimt apparaten, meldingen, concepten, IP-adressen, tickets en de export op', function () {
        Storage::fake('local');

        $user = User::factory()->verified()->create();
        $email = $user->email;
        $user->updatePushSubscription('https://push.example/apparaat', 'sleutel', 'token', 'aes128gcm');
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\OutbidMail',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => 'Overboden op Heftruck']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $draft = Lot::factory()->create(['seller_id' => $user->id, 'status' => LotStatus::Draft]);
        $auction = Auction::factory()->create(['start_price_cents' => 100_000, 'current_price_cents' => 100_000]);
        app(PlaceBid::class)->handle($auction, $user, 100_000, (string) Str::uuid(), ip: '10.1.2.3', userAgent: 'Firefox');
        $ticket = SupportTicket::factory()->create(['user_id' => null, 'email' => $email, 'ip' => '10.1.2.3']);
        Storage::disk('local')->put(ExportUserData::path($user), '{}');

        app(AnonymiseUser::class)->handle($user);

        expect($user->pushSubscriptions()->count())->toBe(0)
            ->and(DB::table('notifications')->where('notifiable_id', $user->id)->count())->toBe(0)
            ->and(Lot::query()->whereKey($draft->id)->exists())->toBeFalse()
            ->and(Bid::query()->where('user_id', $user->id)->count())->toBe(1)
            ->and(Bid::query()->where('user_id', $user->id)->whereNotNull('ip')->count())->toBe(0)
            ->and($ticket->fresh()->email)->not->toBe($email)
            ->and($ticket->fresh()->ip)->toBeNull();

        Storage::disk('local')->assertMissing(ExportUserData::path($user));
    });
});

describe('de inzage-export', function () {
    it('bevat ook berichten, supportverzoeken en aangeboden kavels', function () {
        Storage::fake('local');
        Notification::fake();

        $user = User::factory()->verified()->create();
        $order = Order::factory()->create(['buyer_id' => $user->id, 'status' => OrderStatus::Paid]);
        OrderMessage::create(['order_id' => $order->id, 'user_id' => $user->id, 'from_staff' => false, 'body' => 'Wanneer kan ik ophalen?']);
        SupportTicket::factory()->create(['user_id' => $user->id, 'message' => 'Mijn factuur klopt niet']);
        Lot::factory()->create(['seller_id' => $user->id, 'title' => 'Heftruck Linde H25']);

        (new ExportUserData($user))->handle();

        expect(Storage::disk('local')->get(ExportUserData::path($user)))
            ->toContain('Wanneer kan ik ophalen?')
            ->toContain('Mijn factuur klopt niet')
            ->toContain('Heftruck Linde H25');
    });

    it('wordt na de bewaartermijn van schijf gehaald', function () {
        Storage::fake('local');
        Storage::disk('local')->put('exports/user-1.json', '{}');

        $this->artisan('aucvy:prune-expired')->assertSuccessful();
        Storage::disk('local')->assertExists('exports/user-1.json');

        $this->travel((int) config('aucvy.retention.exports_days') + 1)->days();

        $this->artisan('aucvy:prune-expired')->assertSuccessful();
        Storage::disk('local')->assertMissing('exports/user-1.json');
    });
});

describe('de nepcheckout', function () {
    it('is alleen voor de koper zelf', function () {
        $order = Order::factory()->create();
        $this->actingAs($order->buyer)->post(route('orders.pay', $order));
        $payment = $order->payments()->sole();

        $stranger = User::factory()->verified()->create();

        $this->actingAs($stranger)->get(route('dev.checkout', $payment))->assertForbidden();
        $this->actingAs($stranger)->post(route('dev.checkout.store', $payment), ['outcome' => 'paid'])->assertForbidden();

        expect($order->fresh()->status)->not->toBe(OrderStatus::Paid);
    });
});

describe('zoeken', function () {
    it('vindt een backslash letterlijk', function () {
        $hit = Lot::factory()->live()->create(['title' => 'Netwerkkast \\\\server\\share']);
        Auction::factory()->create(['lot_id' => $hit->id]);
        $miss = Lot::factory()->live()->create(['title' => 'Netwerkkast servershare']);
        Auction::factory()->create(['lot_id' => $miss->id]);

        $this->get('/veilingen?q='.urlencode('server\\share'))
            ->assertOk()
            ->assertSee('server\\share')
            ->assertDontSee('Netwerkkast servershare');
    });
});
