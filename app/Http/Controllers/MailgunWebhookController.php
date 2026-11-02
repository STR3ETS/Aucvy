<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MailDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * De webhook van Mailgun (besluit Raph, 2 november 2026): werkt het
 * afleverlog bij.
 *
 * Alleen een bericht met een geldige handtekening telt: HMAC-SHA256 over
 * timestamp en token met de webhooksleutel, niet ouder dan een kwartier,
 * en elk token maar één keer. Een onbekend bericht-id is geen fout; dat
 * kan een mail van vóór het afleverlog zijn.
 */
final class MailgunWebhookController extends Controller
{
    private const MAX_AGE_SECONDS = 900;

    public function __invoke(Request $request): Response
    {
        $signature = (array) $request->input('signature', []);

        abort_unless($this->validSignature($signature), 406);

        $data = (array) $request->input('event-data', []);
        $messageId = trim((string) data_get($data, 'message.headers.message-id', ''), '<>');
        $status = $this->statusFor((string) ($data['event'] ?? ''), (string) ($data['severity'] ?? ''));

        if ($messageId === '' || $status === null) {
            return response()->noContent();
        }

        $query = MailDelivery::query()->where('message_id', $messageId);

        if (is_string($data['recipient'] ?? null)) {
            $query->where('to_email', Str::lower($data['recipient']));
        }

        $reason = data_get($data, 'delivery-status.description') ?: data_get($data, 'delivery-status.message') ?: ($data['reason'] ?? null);

        foreach ($query->get() as $delivery) {
            // Een definitieve uitkomst wordt niet overschreven door een latere tijdelijke melding.
            if (in_array($delivery->status, MailDelivery::PROBLEMS, true) && $status === MailDelivery::STATUS_DEFERRED) {
                continue;
            }

            $delivery->forceFill([
                'status' => $status,
                'failure_reason' => $status === MailDelivery::STATUS_DELIVERED ? null : Str::limit((string) $reason, 490),
                'last_event_at' => now(),
            ])->save();
        }

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $signature
     */
    private function validSignature(array $signature): bool
    {
        $key = (string) config('services.mailgun.webhook_signing_key');
        $timestamp = (string) ($signature['timestamp'] ?? '');
        $token = (string) ($signature['token'] ?? '');
        $given = (string) ($signature['signature'] ?? '');

        if ($key === '' || $timestamp === '' || $token === '' || $given === '') {
            return false;
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return false;
        }

        if (! hash_equals(hash_hmac('sha256', $timestamp.$token, $key), $given)) {
            return false;
        }

        // Herhaling van hetzelfde bericht weigeren.
        return Cache::add('mailgun-webhook:'.hash('sha256', $token), true, self::MAX_AGE_SECONDS);
    }

    private function statusFor(string $event, string $severity): ?string
    {
        return match ($event) {
            'delivered' => MailDelivery::STATUS_DELIVERED,
            'failed' => $severity === 'temporary' ? MailDelivery::STATUS_DEFERRED : MailDelivery::STATUS_FAILED,
            'complained' => MailDelivery::STATUS_COMPLAINED,
            default => null,
        };
    }
}
