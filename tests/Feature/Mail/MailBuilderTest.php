<?php

declare(strict_types=1);

use App\Admin\StaffRole;
use App\Enums\OrderStatus;
use App\Mail\Automations\AutomationCatalog;
use App\Mail\Automations\AutomationEngine;
use App\Mail\MailCatalog;
use App\Mail\MailSender;
use App\Mail\TemplatedMail;
use App\Models\Auction;
use App\Models\AutomationRun;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\MailAutomation;
use App\Models\MailCampaign;
use App\Models\MailConsent;
use App\Models\MailDelivery;
use App\Models\MailSuppression;
use App\Models\MailTemplate;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
| Mail Builder en reeksen (briefing nieuwe functies): 46 mails met versies,
| dezelfde blokken voor losse mails en reeksen, afmelden dat lopende reeksen
| stopt terwijl service doorgaat, een suppressielijst uit de webhook, en
| reeksstappen die twee keer draaien maar één mail sturen.
*/

function marketingStaff(): User
{
    $user = User::factory()->create(['is_staff' => true]);
    $user->forceFill(['staff_role' => StaffRole::Marketing->value])->save();

    return $user;
}

it('zet de 46 mails en de acht reeksen klaar en laat alleen marketing en eigenaar de builder zien', function () {
    MailCatalog::seed();
    AutomationCatalog::seed();

    expect(MailTemplate::query()->count())->toBe(46)
        ->and(MailTemplate::query()->where('stream', MailTemplate::SERVICE)->count())->toBe(37)
        ->and(MailTemplate::query()->where('stream', MailTemplate::ACTIVATION)->count())->toBe(6)
        ->and(MailTemplate::query()->where('stream', MailTemplate::CAMPAIGN)->count())->toBe(3)
        ->and(MailAutomation::query()->count())->toBe(8)
        ->and(MailTemplate::query()->whereNull('current_version_id')->count())->toBe(0);

    $this->actingAs(marketingStaff())->get(route('admin.mail'))->assertOk()->assertSee('Welkom')->assertSee('Betaalverzoek met herinnering')->assertSee('Nieuwsbrief');

    $support = User::factory()->create(['is_staff' => true]);
    $support->forceFill(['staff_role' => StaffRole::Support->value])->save();
    $this->actingAs($support)->get(route('admin.mail'))->assertForbidden();
});

it('bewaart elke wijziging als nieuwe versie, toont een preview met voorbeelddata en stuurt een testmail', function () {
    Mail::fake();
    MailCatalog::seed();
    $staff = marketingStaff();
    $template = MailTemplate::query()->where('key', 'welkom')->firstOrFail();

    $this->actingAs($staff)->get(route('admin.mail.edit', $template))->assertOk()->assertSee('Opslaan als nieuwe versie');
    $this->actingAs($staff)->get(route('admin.mail.preview', $template))->assertOk()->assertSee('Welkom bij Aucvy')->assertSee('Vertel Ivy wat je zoekt');

    $this->actingAs($staff)->put(route('admin.mail.update', $template), [
        'subject' => 'Welkom {{voornaam}}, ik ben Ivy', 'preheader' => 'Nieuwe tekst', 'sender' => 'ivy',
        'blocks' => json_encode([['type' => 'heading', 'text' => 'Hallo {{voornaam}}'], ['type' => 'button', 'label' => 'Begin', 'url' => '{{links.veilingen}}']]),
    ])->assertRedirect();

    $template->refresh();
    expect($template->versions()->count())->toBe(2)
        ->and($template->currentVersion->version)->toBe(2)
        ->and($template->currentVersion->blocks[0]['text'])->toBe('Hallo {{voornaam}}');

    $this->actingAs($staff)->get(route('admin.mail.preview', $template))->assertOk()->assertSee('Hallo Karin')->assertDontSee('{{voornaam}}');

    $this->actingAs($staff)->post(route('admin.mail.test', $template))->assertRedirect();
    Mail::assertSent(TemplatedMail::class, fn (TemplatedMail $mail) => $mail->hasTo($staff->email) && $mail->rendered->subject === 'Welkom Karin, ik ben Ivy');

    // Een oude versie terugzetten.
    $first = $template->versions()->where('version', 1)->firstOrFail();
    $this->actingAs($staff)->post(route('admin.mail.restore', [$template, $first]))->assertRedirect();
    expect($template->fresh()->current_version_id)->toBe($first->id);
});

it('verstuurt een mail uit de builder met kavel in het dossier en de velden ingevuld', function () {
    $seller = User::factory()->verified()->business()->create(['name' => 'Jos Verkoper']);
    $lot = Lot::factory()->live()->create(['seller_id' => $seller->id, 'company_id' => $seller->company_id, 'title' => 'Koelcel 20 m3']);
    Auction::factory()->create(['lot_id' => $lot->id, 'ends_at' => now()->addDay()]);

    $outcome = app(MailSender::class)->send($seller, 'kavel_live', ['lot' => $lot->fresh('auction')]);

    expect($outcome)->toBeNull();

    $delivery = MailDelivery::query()->where('template_key', 'kavel_live')->firstOrFail();
    expect($delivery->subject)->toBe('Koelcel 20 m3 staat live')
        ->and($delivery->stream)->toBe('service')
        ->and($delivery->lot_id)->toBe($lot->id)
        ->and(LotActivity::query()->where('lot_id', $lot->id)->where('source', 'mail')->exists())->toBeTrue();
});

it('laat afmelden de marketingreeksen stoppen terwijl servicemail doorgaat, en slaat campagnes over zonder toestemming', function () {
    MailCatalog::seed();
    AutomationCatalog::seed();
    $buyer = User::factory()->verified()->create();
    $automation = MailAutomation::query()->where('key', 'dormant_buyer')->firstOrFail();
    $run = AutomationRun::create(['automation_id' => $automation->id, 'user_id' => $buyer->id, 'current_step' => 1, 'next_run_at' => now()->addDay(), 'status' => AutomationRun::RUNNING, 'dedupe_key' => 'test:'.$buyer->id]);

    $url = MailSender::unsubscribeUrl($buyer, MailTemplate::ACTIVATION);
    $this->get($url)->assertOk()->assertSee('Ja, meld me af');
    $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertNoContent();

    expect(MailConsent::statusFor($buyer, MailTemplate::ACTIVATION))->toBe(MailConsent::REVOKED)
        ->and($run->fresh()->status)->toBe(AutomationRun::STOPPED)
        ->and($run->fresh()->stop_reason)->toBe('unsubscribed');

    // Activatie wordt geweigerd, service gaat gewoon.
    expect(app(MailSender::class)->send($buyer, 'slapende_koper'))->toBe('unsubscribed')
        ->and(app(MailSender::class)->send($buyer, 'betaalherinnering', ['order' => Order::factory()->create(['buyer_id' => $buyer->id])]))->toBeNull();

    // Zonder getekende link geen afmelding.
    $this->get(route('mail.unsubscribe', [$buyer, MailTemplate::ACTIVATION]))->assertForbidden();

    // Campagne: alleen wie ja zei.
    $yes = User::factory()->verified()->create();
    MailConsent::set($yes, MailTemplate::CAMPAIGN, MailConsent::GRANTED, 'test');
    $blocked = User::factory()->verified()->create();
    MailConsent::set($blocked, MailTemplate::CAMPAIGN, MailConsent::GRANTED, 'test');
    MailSuppression::add($blocked->email, 'complaint', 'test');

    $template = MailTemplate::query()->where('key', 'nieuwe_kavels_week')->firstOrFail();
    $campaign = MailCampaign::create(['template_version_id' => $template->current_version_id, 'name' => 'Weekmail', 'segment' => ['audience' => 'all'], 'scheduled_at' => now()->subMinute(), 'status' => MailCampaign::SCHEDULED]);

    $this->artisan('aucvy:send-campaigns')->assertSuccessful();

    $campaign->refresh();
    expect($campaign->status)->toBe(MailCampaign::SENT)
        ->and($campaign->sent_count)->toBe(1)
        ->and(MailDelivery::query()->where('campaign_id', $campaign->id)->where('user_id', $yes->id)->exists())->toBeTrue()
        ->and(MailDelivery::query()->where('campaign_id', $campaign->id)->where('user_id', $blocked->id)->exists())->toBeFalse()
        ->and(MailDelivery::query()->where('campaign_id', $campaign->id)->where('user_id', $buyer->id)->exists())->toBeFalse();
});

it('stuurt de betaalherinnering na 12 uur, één keer, en stopt als er betaald is', function () {
    $order = Order::factory()->create(['payment_deadline_at' => now()->addDay()]);
    $paid = Order::factory()->create(['payment_deadline_at' => now()->addDay()]);

    $this->artisan('aucvy:automations')->assertSuccessful();

    $run = AutomationRun::query()->where('order_id', $order->id)->firstOrFail();
    expect($run->status)->toBe(AutomationRun::RUNNING)->and($run->next_run_at->diffInHours(now(), true))->toBeGreaterThan(11)
        ->and(MailDelivery::query()->where('template_key', 'betaalherinnering')->count())->toBe(0);

    $paid->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();

    $this->travel(13)->hours();
    $this->artisan('aucvy:automations')->assertSuccessful();
    $this->artisan('aucvy:automations')->assertSuccessful();

    expect(MailDelivery::query()->where('template_key', 'betaalherinnering')->where('user_id', $order->buyer_id)->count())->toBe(1)
        ->and($run->fresh()->status)->toBe(AutomationRun::DONE)
        ->and(AutomationRun::query()->where('order_id', $paid->id)->value('status'))->toBe(AutomationRun::STOPPED)
        ->and(MailDelivery::query()->where('user_id', $paid->buyer_id)->where('template_key', 'betaalherinnering')->exists())->toBeFalse();

    // Dezelfde stap nog eens verwerken geeft geen tweede mail.
    app(AutomationEngine::class)->process($run->fresh(['automation', 'user', 'order']));
    expect(MailDelivery::query()->where('template_key', 'betaalherinnering')->count())->toBe(1);
});

it('mailt een slapende koper één keer en telt klikken en klachten uit de webhook', function () {
    $dormant = User::factory()->verified()->create();
    $dormant->forceFill(['last_login_at' => now()->subMonths(7)])->save();
    $active = User::factory()->verified()->create();
    $active->forceFill(['last_login_at' => now()->subDays(3)])->save();

    $this->artisan('aucvy:automations')->assertSuccessful();
    $this->artisan('aucvy:automations')->assertSuccessful();

    expect(MailDelivery::query()->where('template_key', 'slapende_koper')->where('user_id', $dormant->id)->count())->toBe(1)
        ->and(MailDelivery::query()->where('template_key', 'slapende_koper')->where('user_id', $active->id)->count())->toBe(0);

    $delivery = MailDelivery::query()->where('template_key', 'slapende_koper')->firstOrFail();
    $delivery->forceFill(['message_id' => 'msg-1@aucvy.test'])->save();

    config(['services.mailgun.webhook_signing_key' => 'sleutel']);
    $signed = function (array $event) use ($delivery): array {
        $timestamp = (string) now()->getTimestamp();
        $token = bin2hex(random_bytes(8));

        return ['signature' => ['timestamp' => $timestamp, 'token' => $token, 'signature' => hash_hmac('sha256', $timestamp.$token, 'sleutel')], 'event-data' => $event + ['message' => ['headers' => ['message-id' => 'msg-1@aucvy.test']], 'recipient' => $delivery->to_email]];
    };

    $this->post(route('webhooks.mailgun'), $signed(['event' => 'clicked']))->assertNoContent();
    expect($delivery->fresh()->clicked_at)->not->toBeNull();

    $this->post(route('webhooks.mailgun'), $signed(['event' => 'complained']))->assertNoContent();
    expect(MailSuppression::has($dormant->email))->toBeTrue()
        ->and(app(MailSender::class)->send($dormant, 'slapende_koper'))->toBe('suppressed');
});
