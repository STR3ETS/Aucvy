<?php

declare(strict_types=1);

use App\Auctions\Actions\PlaceBid;
use App\Live\FakeLiveProvider;
use App\Live\LiveKitProvider;
use App\Live\LiveProvider;
use App\Live\LiveSessions;
use App\Models\Auction;
use App\Models\LiveSession;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\User;
use App\Notifications\LiveSessionNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/*
| Live veilen (briefing nieuwe functies): achter een vlag; alleen een
| geverifieerde zakelijke verkoper op een eigen lopende kavel; eerst de
| check en de aftelling, dan live; kijken zonder account; de server blijft
| de enige waarheid voor het bod, ook als het beeld wegvalt.
*/

beforeEach(function () {
    config(['aucvy.live.enabled' => true, 'aucvy.live.provider' => 'fake']);
    $this->provider = new FakeLiveProvider;
    app()->instance(LiveProvider::class, $this->provider);
});

function liveLotForBroadcast(): Lot
{
    $seller = User::factory()->verified()->business()->create();
    $lot = Lot::factory()->live()->create(['seller_id' => $seller->id, 'company_id' => $seller->company_id]);
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addHours(2)]);

    return $lot->fresh(['auction', 'seller']);
}

it('staat uit zonder vlag en laat alleen de geverifieerde verkoper van een lopende kavel live gaan', function () {
    $lot = liveLotForBroadcast();

    config(['aucvy.live.enabled' => false]);
    $this->actingAs($lot->seller)->get(route('dashboard.lots.live', $lot))->assertNotFound();
    config(['aucvy.live.enabled' => true]);

    $this->actingAs($lot->seller)->get(route('dashboard.lots.live', $lot))->assertOk()->assertSee('Kamer klaarzetten');

    $other = User::factory()->verified()->business()->create();
    $this->actingAs($other)->get(route('dashboard.lots.live', $lot))->assertNotFound();

    $private = User::factory()->verified()->create();
    $ownLot = Lot::factory()->live()->create(['seller_id' => $private->id, 'company_id' => null]);
    Auction::factory()->create(['lot_id' => $ownLot->id, 'ends_at' => now()->addHour()]);
    $this->actingAs($private)->get(route('dashboard.lots.live', $ownLot))->assertOk()->assertSee('geverifieerde zakelijke verkoper');
    $this->actingAs($private)->post(route('dashboard.lots.live.start', $ownLot))->assertSessionHasErrors('live');
});

it('zet een kamer klaar, gaat na de aftelling live, geeft kijkers een alleen-kijken-token en stopt met een dossierregel', function () {
    $lot = liveLotForBroadcast();

    $this->actingAs($lot->seller)->post(route('dashboard.lots.live.start', $lot))->assertRedirect();
    $session = LiveSession::query()->where('lot_id', $lot->id)->firstOrFail();

    expect($session->status)->toBe(LiveSession::PREPARING)
        ->and($this->provider->calls[0]['method'])->toBe('createRoom')
        ->and($session->max_viewers)->toBe((int) config('aucvy.live.max_viewers'));

    // Twee keer starten kan niet.
    $this->actingAs($lot->seller)->post(route('dashboard.lots.live.start', $lot))->assertSessionHasErrors('live');

    // Kijkers krijgen nog niets zolang de aftelling niet is afgelopen.
    $this->getJson('/api/lots/'.$lot->id.'/live')->assertOk()->assertJsonPath('live', false);

    $this->actingAs($lot->seller)->postJson(route('dashboard.lots.live.go', [$lot, $session]))->assertOk()->assertJsonPath('status', 'live');
    expect(LotActivity::query()->where('lot_id', $lot->id)->where('source', 'live')->where('type', 'started')->exists())->toBeTrue();

    // De studio toont een zendtoken; de kijker, ook zonder account, alleen een kijktoken.
    $this->actingAs($lot->seller)->get(route('dashboard.lots.live', $lot))->assertOk()->assertSee('fake.'.$session->room.'.verkoper-'.$lot->seller_id.'.publish');
    Auth::logout();
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/lots/'.$lot->id.'/live')->assertOk()->assertJsonPath('live', true)->assertJsonPath('token', fn ($token) => str_ends_with($token, '.view'));
    $this->get(route('lots.show', $lot))->assertOk()->assertSee('Live vanaf de kavel');

    // Webhook: kijkers tellen, de verkoper niet.
    $this->postJson(route('webhooks.live'), ['event' => 'participant_joined', 'room' => $session->room, 'participants' => 4], ['X-Live-Secret' => 'fake'])->assertNoContent();
    expect($session->fresh()->viewers_now)->toBe(3)->and($session->fresh()->peak_viewers)->toBe(3);
    $this->postJson(route('webhooks.live'), ['event' => 'participant_joined', 'room' => $session->room, 'participants' => 4], ['X-Live-Secret' => 'fout'])->assertForbidden();

    // Bieden loopt via de biedmotor, los van de video.
    $bidder = User::factory()->verified()->create();
    app(PlaceBid::class)->handle($lot->auction->fresh(), $bidder, $lot->auction->fresh()->current_price_cents + 10_000, 'live-bod');

    $this->actingAs($lot->seller)->post(route('dashboard.lots.live.end', [$lot, $session]))->assertRedirect();
    $session->refresh();
    expect($session->status)->toBe(LiveSession::ENDED)
        ->and($session->end_reason)->toBe('ended_by_seller')
        ->and(LotActivity::query()->where('lot_id', $lot->id)->where('source', 'live')->where('type', 'ended')->exists())->toBeTrue()
        ->and($lot->auction->fresh()->isClosed())->toBeFalse();

    $this->getJson('/api/lots/'.$lot->id.'/live')->assertOk()->assertJsonPath('live', false);
});

it('laat de veiling doorlopen als de kamer wegvalt en waarschuwt de verkoper direct', function () {
    Notification::fake();
    $lot = liveLotForBroadcast();
    $session = app(LiveSessions::class)->start($lot, $lot->seller);
    app(LiveSessions::class)->goLive($session);

    $this->postJson(route('webhooks.live'), ['event' => 'room_finished', 'room' => $session->room], ['X-Live-Secret' => 'fake'])->assertNoContent();

    expect($session->fresh()->status)->toBe(LiveSession::ENDED)
        ->and($session->fresh()->end_reason)->toBe('room_finished')
        ->and($lot->auction->fresh()->isClosed())->toBeFalse();
    Notification::assertSentTo($lot->seller, LiveSessionNotice::class);
});

it('verifieert de handtekening van LiveKit en maakt geldige toegangstokens', function () {
    $provider = new LiveKitProvider('https://live.example', 'APIKEY', 'geheim', 'wss://live.example');

    $token = $provider->token('kamer-1', 'kijker-1', 'Kijker', false);
    [$header, $payload, $signature] = explode('.', $token);
    $claims = json_decode(base64_decode(strtr($payload, '-_', '+/'), true), true);

    expect($claims['iss'])->toBe('APIKEY')
        ->and($claims['sub'])->toBe('kijker-1')
        ->and($claims['video']['canPublish'])->toBeFalse()
        ->and($claims['video']['room'])->toBe('kamer-1');

    $body = json_encode(['event' => 'room_finished', 'room' => ['name' => 'kamer-1', 'numParticipants' => 0]]);
    $sha = base64_encode(hash('sha256', $body, true));
    $signed = (function () use ($sha) {
        $encode = fn (array $data) => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');
        $header = $encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $claims = $encode(['iss' => 'APIKEY', 'exp' => now()->getTimestamp() + 60, 'sha256' => $sha]);
        $sig = rtrim(strtr(base64_encode(hash_hmac('sha256', $header.'.'.$claims, 'geheim', true)), '+/', '-_'), '=');

        return $header.'.'.$claims.'.'.$sig;
    })();

    $request = Request::create('/webhooks/live', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => $signed, 'CONTENT_TYPE' => 'application/json'], $body);
    expect($provider->webhook($request))->toBe(['event' => 'room_finished', 'room' => 'kamer-1', 'participants' => 0, 'participant_identity' => null]);

    $tampered = Request::create('/webhooks/live', 'POST', [], [], [], ['HTTP_AUTHORIZATION' => $signed, 'CONTENT_TYPE' => 'application/json'], '{"event":"room_finished"}');
    expect($provider->webhook($tampered))->toBeNull();
});
