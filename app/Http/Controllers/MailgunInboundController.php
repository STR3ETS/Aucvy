<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Admin\Tickets;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Inkomende mail via een Mailgun-route wordt een ticket; een antwoord op
 * een bestaand ticket (het nummer in het onderwerp) wordt een bericht in
 * dezelfde draad. Zelfde handtekeningcontrole als de andere webhook.
 */
final class MailgunInboundController extends Controller
{
    public function __invoke(Request $request, Tickets $tickets): Response
    {
        abort_unless($this->validSignature($request), 406);

        $from = (string) $request->input('sender', $request->input('from', ''));
        $email = Str::lower(trim((string) (preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : $from)));
        $name = trim((string) preg_replace('/<[^>]+>/', '', (string) $request->input('from', $email))) ?: $email;
        $subject = trim((string) $request->input('subject', 'Mail zonder onderwerp'));
        $body = trim((string) ($request->input('stripped-text') ?: $request->input('body-plain', '')));

        if ($email === '' || $body === '') {
            return response()->noContent();
        }

        if (preg_match('/\[(T-\d{4,})\]/', $subject, $match) === 1) {
            $ticket = SupportTicket::query()->where('number', $match[1])->first();

            if ($ticket !== null) {
                $tickets->append($ticket, $body);

                return response()->noContent();
            }
        }

        $tickets->open([
            'name' => Str::limit($name, 120, ''),
            'email' => $email,
            'subject' => Str::limit($subject, 150, ''),
            'message' => Str::limit($body, 5000, ''),
            'source' => 'mail',
            'mail_thread' => (string) $request->input('Message-Id', ''),
        ]);

        return response()->noContent();
    }

    private function validSignature(Request $request): bool
    {
        $key = (string) config('services.mailgun.webhook_signing_key');
        $timestamp = (string) $request->input('timestamp', '');
        $token = (string) $request->input('token', '');
        $signature = (string) $request->input('signature', '');

        if ($key === '' || $timestamp === '' || $token === '' || $signature === '') {
            return false;
        }

        return abs(now()->getTimestamp() - (int) $timestamp) <= 900
            && hash_equals(hash_hmac('sha256', $timestamp.$token, $key), $signature);
    }
}
