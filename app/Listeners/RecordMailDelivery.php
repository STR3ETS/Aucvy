<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\MailDelivery;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Str;
use Throwable;

/**
 * Legt elke verzonden mail vast in het afleverlog, met het bericht-id dat
 * Mailgun teruggaf; de webhook koppelt zijn meldingen daaraan. Een fout
 * hier mag het verzenden nooit laten mislukken.
 */
final class RecordMailDelivery
{
    public function handle(MessageSent $event): void
    {
        try {
            $messageId = $event->sent->getMessageId();
            $notification = $event->data['__laravel_notification'] ?? null;
            $mailer = (string) config('mail.default');
            $headers = $event->message->getHeaders();

            // Het kavel en de order uit de metadata (zie TagMailWithLot), zodat
            // de mail in het kaveldossier komt.
            $lotId = $headers->has('X-Metadata-lot_id') ? (int) $headers->get('X-Metadata-lot_id')->getBodyAsString() : null;
            $orderId = $headers->has('X-Metadata-order_id') ? (int) $headers->get('X-Metadata-order_id')->getBodyAsString() : null;

            foreach ($event->message->getTo() as $address) {
                $email = Str::lower($address->getAddress());

                MailDelivery::create([
                    'message_id' => $messageId === '' ? null : trim($messageId, '<>'),
                    'to_email' => $email,
                    'user_id' => User::query()->where('email', $email)->value('id'),
                    'lot_id' => $lotId ?: null,
                    'order_id' => $orderId ?: null,
                    'subject' => Str::limit((string) $event->message->getSubject(), 290),
                    'kind' => is_string($notification) ? class_basename($notification) : null,
                    'mailer' => $mailer,
                    'status' => MailDelivery::STATUS_SENT,
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
