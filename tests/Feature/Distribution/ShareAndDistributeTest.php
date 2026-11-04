<?php

declare(strict_types=1);

use App\Auctions\Events\AuctionClosed;
use App\Distribution\Channel;
use App\Distribution\Publishers\PublisherFactory;
use App\Distribution\ShareKit;
use App\Distribution\ShareVisual;
use App\Distribution\SocialPlanning;
use App\Jobs\DistributeLotToChannel;
use App\Jobs\WithdrawLotFromChannels;
use App\Models\Auction;
use App\Models\ChannelConnection;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\LotDistribution;
use App\Models\LotShare;
use App\Models\User;
use App\Notifications\DistributionUpdate;
use App\Notifications\ShareReaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/*
| Delen en verspreiden (briefing nieuwe functies): een eigen deelbeeld per
| kavel, de deling door een koper met token en notitie, de deelkit van de
| verkoper, de social push met één akkoord en offline bij sluiting.
*/

beforeEach(fn () => Storage::fake('public'));

function liveLotToShare(): Lot
{
    $seller = User::factory()->verified()->business()->create();
    $lot = Lot::factory()->live()->create(['seller_id' => $seller->id, 'company_id' => $seller->company_id, 'title' => 'Hyster heftruck 2,5 ton']);
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDays(2)]);

    return $lot->fresh(['auction', 'seller', 'media', 'category']);
}

it('maakt per kavel een eigen deelbeeld in drie formaten en zet het als Open Graph-afbeelding', function () {
    $lot = liveLotToShare();

    foreach (ShareVisual::FORMATS as $format) {
        $response = $this->get(route('lots.share-image', [$lot, $format]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        [$width, $height] = ShareVisual::dimensions($format);
        $size = getimagesizefromstring((string) $response->getFile()->getContent());
        expect($size[0])->toBe($width)->and($size[1])->toBe($height);
    }

    $this->get(route('lots.show', $lot))
        ->assertOk()
        ->assertSee('property="og:image" content="'.route('lots.share-image', [$lot, 'landscape']).'"', false)
        ->assertSee('og:image:width" content="1200"', false)
        ->assertSee('og:image:height" content="630"', false);

    $draft = Lot::factory()->create();
    $this->get(route('lots.share-image', [$draft, 'square']))->assertNotFound();
});

it('laat een koper delen met notitie; de ontvanger ziet de notitie, nooit het bod, en kan één keer reageren', function () {
    Notification::fake();
    $lot = liveLotToShare();
    $buyer = User::factory()->verified()->create();

    $response = $this->actingAs($buyer)->postJson(route('lots.share', $lot), ['channel' => 'whatsapp', 'note' => 'Wat vind jij van de staat?'])->assertCreated();
    $url = $response->json('url');
    $share = LotShare::query()->firstOrFail();

    expect($url)->toContain('/d/'.$share->token)->toContain('utm_source=deel')->toContain('utm_medium=whatsapp')
        ->and($share->user_id)->toBe($buyer->id)
        ->and(LotActivity::query()->where('lot_id', $lot->id)->where('type', 'shared')->where('user_id', $buyer->id)->exists())->toBeTrue();

    // De ontvanger, zonder account.
    Auth::logout();
    $this->app['auth']->forgetGuards();
    $this->flushSession();
    $this->get($url)->assertOk()->assertSee('Wat vind jij van de staat?')->assertSee('Iemand deelde deze kavel met je')->assertDontSee($buyer->publicName());
    expect($share->fresh()->opened_at)->not->toBeNull()->and($share->fresh()->open_count)->toBe(1);

    $this->post(route('lots.shared.react', [$lot, $share->token]), ['reaction' => 'interested'])->assertRedirect();
    expect($share->fresh()->reaction)->toBe('interested');
    Notification::assertSentTo($buyer, ShareReaction::class);

    // Een tweede reactie telt niet.
    $this->post(route('lots.shared.react', [$lot, $share->token]), ['reaction' => 'not_interested'])->assertRedirect();
    expect($share->fresh()->reaction)->toBe('interested');

    // De deler ziet dat de link is geopend en wat de reactie was.
    $this->actingAs($buyer)->get(route('lots.show', $lot))->assertOk()->assertSee('1× geopend')->assertSee('Interessant');

    // Een gast mag ook delen; een verkeerd kanaal niet.
    $this->postJson(route('lots.share', $lot), ['channel' => 'link'])->assertCreated();
    $this->postJson(route('lots.share', $lot), ['channel' => 'telegram'])->assertUnprocessable();
    $this->get(route('lots.shared', [$lot, 'bestaatniet12']))->assertNotFound();
});

it('geeft de verkoper een deelkit per kanaal met tekst, beeld en link, en telt klikken uit de UTM', function () {
    $lot = liveLotToShare();
    $seller = $lot->seller;

    $this->actingAs($seller)->get(route('dashboard.lots.show', [$lot, 'tab' => 'verspreiding']))
        ->assertOk()
        ->assertSee('Deelkit per kanaal')
        ->assertSee('Facebook')->assertSee('Marktplaats')->assertSee('WhatsApp')
        ->assertSee('utm_source=linkedin', false)
        ->assertSee('Vierkant (1080');

    $facebook = LotDistribution::query()->where('lot_id', $lot->id)->where('channel', 'facebook')->where('moment', 'kit')->firstOrFail();
    expect($facebook->body)->toContain('{bod}')->toContain('Hyster heftruck')
        ->and($facebook->renderedBody())->not->toContain('{bod}')->toContain('utm_source=facebook');

    $this->actingAs($seller)->post(route('dashboard.lots.distribution.text', [$lot, $facebook]), ['body' => 'Eigen tekst over de heftruck. {link}'])->assertRedirect();
    expect($facebook->fresh()->body_source)->toBe('seller');

    $this->actingAs($seller)->post(route('dashboard.lots.distribution.posted', [$lot, $facebook]), ['url' => 'https://www.facebook.com/post/1'])->assertRedirect();
    expect($facebook->fresh()->status)->toBe(LotDistribution::POSTED)
        ->and(LotActivity::query()->where('lot_id', $lot->id)->where('source', 'distribution')->where('type', 'posted')->exists())->toBeTrue();

    // Twee bezoeken in één sessie tellen één keer; een andere sessie telt weer.
    $this->get(ShareKit::link($lot, Channel::Facebook))->assertOk();
    $this->get(ShareKit::link($lot, Channel::Facebook))->assertOk();
    expect($facebook->fresh()->clicks)->toBe(1);
    $this->flushSession();
    $this->get(ShareKit::link($lot, Channel::Facebook))->assertOk();
    expect($facebook->fresh()->clicks)->toBe(2);

    // Een andere verkoper komt er niet in.
    $this->actingAs(User::factory()->verified()->business()->create())->post(route('dashboard.lots.distribution.posted', [$lot, $facebook]))->assertNotFound();
});

it('laat Nova de teksten schrijven zonder een eigen tekst van de verkoper te overschrijven', function () {
    $lot = liveLotToShare();
    $seller = $lot->seller;

    $this->actingAs($seller)->get(route('dashboard.lots.show', [$lot, 'tab' => 'verspreiding']))->assertOk();
    $linkedin = LotDistribution::query()->where('lot_id', $lot->id)->where('channel', 'linkedin')->where('moment', 'kit')->firstOrFail();
    $this->actingAs($seller)->post(route('dashboard.lots.distribution.text', [$lot, $linkedin]), ['body' => 'Mijn eigen LinkedIn-tekst. {link}'])->assertRedirect();

    $this->actingAs($seller)->post(route('dashboard.lots.distribution.ivy', $lot))->assertRedirect();

    $facebook = LotDistribution::query()->where('lot_id', $lot->id)->where('channel', 'facebook')->where('moment', 'kit')->firstOrFail();
    expect($facebook->body_source)->toBe('ivy')
        ->and($facebook->body)->toContain('{bod}')
        ->and($linkedin->fresh()->body)->toBe('Mijn eigen LinkedIn-tekst. {link}');
});

it('plant de social push in met één akkoord, plaatst op het moment zelf en zet alles offline bij sluiting', function () {
    Notification::fake();
    $lot = liveLotToShare();
    $seller = $lot->seller;

    $planned = app(SocialPlanning::class)->for($lot);
    expect($planned)->toHaveCount(8)
        ->and($planned->where('moment', 'start')->pluck('channel')->sort()->values()->all())->toBe(['facebook', 'instagram', 'marktplaats'])
        ->and($planned->firstWhere('moment', 'day_before')->scheduled_at->toDateTimeString())->toBe($lot->auction->ends_at->copy()->subDay()->toDateTimeString());

    // Zonder akkoord gebeurt er niets.
    $this->artisan('aucvy:social-push')->assertSuccessful();
    expect(LotDistribution::query()->where('lot_id', $lot->id)->where('status', '!=', LotDistribution::DRAFT)->count())->toBe(0);

    $this->actingAs($seller)->post(route('dashboard.lots.distribution.approve', $lot))->assertRedirect();
    expect(LotDistribution::query()->where('lot_id', $lot->id)->where('status', LotDistribution::SCHEDULED)->count())->toBe(8)
        ->and(LotActivity::query()->where('lot_id', $lot->id)->where('type', 'planned')->exists())->toBeTrue();

    // Het startmoment is nu; zonder koppeling staat het bericht klaar voor de verkoper.
    $this->artisan('aucvy:social-push')->assertSuccessful();
    $start = LotDistribution::query()->where('lot_id', $lot->id)->where('moment', 'start')->get();
    expect($start->pluck('status')->unique()->all())->toBe([LotDistribution::READY])
        ->and($start->first()->visual_path)->toStartWith('share/')
        ->and(Storage::disk('public')->exists($start->first()->visual_path))->toBeTrue();
    Notification::assertSentTo($seller, DistributionUpdate::class, fn (DistributionUpdate $notification) => $notification->distribution->status === LotDistribution::READY);

    // De dag ervoor: pas als het zover is.
    expect(LotDistribution::query()->where('lot_id', $lot->id)->where('moment', 'day_before')->where('status', LotDistribution::SCHEDULED)->count())->toBe(3);
    $this->travelTo($lot->auction->ends_at->copy()->subDay()->addMinute());
    $this->artisan('aucvy:social-push')->assertSuccessful();
    expect(LotDistribution::query()->where('lot_id', $lot->id)->where('moment', 'day_before')->where('status', LotDistribution::READY)->count())->toBe(3);

    // Sluiting: alles offline, ook wat nog gepland stond.
    AuctionClosed::dispatch($lot->auction->fresh());
    expect(LotDistribution::query()->where('lot_id', $lot->id)->where('status', LotDistribution::OFFLINE)->count())->toBe(8);
});

it('plaatst niets als de kavel niet meer live is', function () {
    Notification::fake();
    $lot = liveLotToShare();
    $row = app(SocialPlanning::class)->for($lot)->firstWhere('moment', 'start');
    $row->forceFill(['status' => LotDistribution::SCHEDULED, 'approved_at' => now(), 'approved_by' => $lot->seller_id])->save();

    $lot->auction->forceFill(['closed_at' => now()])->save();

    (new DistributeLotToChannel($row->id))->handle(app(PublisherFactory::class), app(ShareVisual::class));

    expect($row->fresh()->status)->toBe(LotDistribution::OFFLINE)->and($row->fresh()->last_error)->toContain('niet (meer) live');
    Notification::assertNothingSent();
});

it('toont de koppelingen met hun status en houdt tokens versleuteld', function () {
    $seller = User::factory()->verified()->business()->create();

    $this->actingAs($seller)->get(route('account.connections'))->assertOk()->assertSee('Facebook')->assertSee('Koppeling volgt')->assertSee('LinkedIn');

    $this->actingAs($seller)->get(route('account.connections.connect', 'facebook'))->assertRedirect(route('account.connections'))->assertSessionHasErrors('channel');

    config(['services.linkedin.client_id' => 'id', 'services.linkedin.client_secret' => 'geheim']);
    $this->actingAs($seller)->get(route('account.connections.connect', 'linkedin'))->assertRedirectContains('linkedin.com/oauth/v2/authorization');
    expect(ChannelConnection::query()->where('user_id', $seller->id)->where('channel', 'linkedin')->value('status'))->toBe(ChannelConnection::PENDING);

    $connection = ChannelConnection::query()->where('user_id', $seller->id)->where('channel', 'linkedin')->firstOrFail();
    $connection->forceFill(['tokens' => ['access_token' => 'geheim-token'], 'status' => ChannelConnection::CONNECTED, 'external_id' => 'abc'])->save();

    expect(DB::table('channel_connections')->where('id', $connection->id)->value('tokens'))->not->toContain('geheim-token')
        ->and($connection->fresh()->token())->toBe('geheim-token')
        ->and($connection->fresh()->isUsable())->toBeTrue();

    $this->actingAs($seller)->get(route('account.connections'))->assertOk()->assertSee('Gekoppeld');
    $this->actingAs($seller)->delete(route('account.connections.disconnect', 'linkedin'))->assertRedirect();
    expect(ChannelConnection::query()->count())->toBe(0);
});

it('haalt de advertenties offline als de verkoper de kavel pauzeert', function () {
    $lot = liveLotToShare();
    $row = app(SocialPlanning::class)->for($lot)->firstWhere('moment', 'last_hour');
    $row->forceFill(['status' => LotDistribution::POSTED, 'posted_at' => now()])->save();

    (new WithdrawLotFromChannels($lot->id))->handle(app(PublisherFactory::class));

    expect($row->fresh()->status)->toBe(LotDistribution::OFFLINE)
        ->and(LotActivity::query()->where('lot_id', $lot->id)->where('type', 'withdrawn')->exists())->toBeTrue();
});
