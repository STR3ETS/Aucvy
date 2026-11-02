<?php

declare(strict_types=1);

use App\Models\MailDelivery;
use App\Models\User;
use App\Notifications\TwoFactorCode;

/*
| Mailgun (besluit Raph, 2 november 2026): elke verzonden mail in het
| afleverlog, en de webhook werkt de status bij. Alleen met een geldige
| handtekening, en elk bericht maar één keer.
*/

beforeEach(function () {
    config(['services.mailgun.webhook_signing_key' => 'test-sleutel']);
});

/**
 * @param  array<string, mixed>  $eventData
 * @return array<string, mixed>
 */
function mailgunPayload(array $eventData, ?string $key = 'test-sleutel', ?int $timestamp = null, ?string $token = null): array
{
    $timestamp ??= now()->getTimestamp();
    $token ??= bin2hex(random_bytes(16));

    return [
        'signature' => [
            'timestamp' => (string) $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp.$token, (string) $key),
        ],
        'event-data' => $eventData,
    ];
}

it('legt een verzonden mail vast met ontvanger, onderwerp en soort', function () {
    $user = User::factory()->create(['email' => 'Anna@Voorbeeld.nl']);

    $user->notify(new TwoFactorCode('123456'));

    $delivery = MailDelivery::query()->sole();

    expect($delivery->to_email)->toBe('anna@voorbeeld.nl')
        ->and($delivery->user_id)->toBe($user->id)
        ->and($delivery->kind)->toBe('TwoFactorCode')
        ->and($delivery->subject)->toContain('123456')
        ->and($delivery->status)->toBe(MailDelivery::STATUS_SENT)
        ->and($delivery->message_id)->not->toBeNull();
});

it('werkt de status bij na een geldige melding van Mailgun', function () {
    $delivery = MailDelivery::create(['message_id' => 'abc@mg.aucvy.nl', 'to_email' => 'anna@voorbeeld.nl', 'mailer' => 'mailgun']);

    $this->postJson(route('webhooks.mailgun'), mailgunPayload([
        'event' => 'failed',
        'severity' => 'permanent',
        'recipient' => 'anna@voorbeeld.nl',
        'message' => ['headers' => ['message-id' => 'abc@mg.aucvy.nl']],
        'delivery-status' => ['description' => 'Mailbox does not exist'],
    ]))->assertNoContent();

    expect($delivery->fresh()->status)->toBe(MailDelivery::STATUS_FAILED)
        ->and($delivery->fresh()->failure_reason)->toBe('Mailbox does not exist');

    // Een latere tijdelijke melding overschrijft een bounce niet.
    $this->postJson(route('webhooks.mailgun'), mailgunPayload([
        'event' => 'failed',
        'severity' => 'temporary',
        'message' => ['headers' => ['message-id' => 'abc@mg.aucvy.nl']],
    ]))->assertNoContent();

    expect($delivery->fresh()->status)->toBe(MailDelivery::STATUS_FAILED);
});

it('weigert een melding met een foute, oude of herhaalde handtekening', function () {
    $delivery = MailDelivery::create(['message_id' => 'abc@mg.aucvy.nl', 'to_email' => 'anna@voorbeeld.nl', 'mailer' => 'mailgun']);
    $data = ['event' => 'delivered', 'message' => ['headers' => ['message-id' => 'abc@mg.aucvy.nl']]];

    $this->postJson(route('webhooks.mailgun'), mailgunPayload($data, key: 'verkeerd'))->assertStatus(406);
    $this->postJson(route('webhooks.mailgun'), mailgunPayload($data, timestamp: now()->subHour()->getTimestamp()))->assertStatus(406);

    $payload = mailgunPayload($data, token: 'eenmalig');
    $this->postJson(route('webhooks.mailgun'), $payload)->assertNoContent();
    $this->postJson(route('webhooks.mailgun'), $payload)->assertStatus(406);

    expect($delivery->fresh()->status)->toBe(MailDelivery::STATUS_DELIVERED);
});

it('weigert alles zolang er geen webhooksleutel is', function () {
    config(['services.mailgun.webhook_signing_key' => null]);

    $this->postJson(route('webhooks.mailgun'), mailgunPayload(['event' => 'delivered'], key: ''))->assertStatus(406);
});

it('toont het afleverlog op de systeempagina', function () {
    MailDelivery::create(['message_id' => 'x@mg', 'to_email' => 'bob@voorbeeld.nl', 'subject' => 'Je inlogcode', 'mailer' => 'mailgun', 'status' => MailDelivery::STATUS_COMPLAINED]);

    $this->actingAs(User::factory()->create(['is_staff' => true]))
        ->get(route('admin.system'))
        ->assertOk()
        ->assertSee('bob@voorbeeld.nl')
        ->assertSee('spammelding');
});
