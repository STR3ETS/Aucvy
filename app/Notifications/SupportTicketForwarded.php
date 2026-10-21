<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Het bericht zelf, naar de supportinbox. Beantwoorden gaat gewoon per
 * mail (reply-to is de afzender); sluiten gebeurt in de backoffice.
 */
final class SupportTicketForwarded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SupportTicket $ticket)
    {
        $this->afterCommit = true;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->ticket;

        $mail = (new MailMessage)
            ->subject(__('support.forwarded.subject', ['id' => $ticket->id, 'subject' => $ticket->subject]))
            ->replyTo($ticket->email, $ticket->name)
            ->line(__('support.forwarded.from', ['name' => Markdown::escape($ticket->name), 'email' => $ticket->email]));

        if ($ticket->order_number !== null) {
            $mail->line(__('support.forwarded.order', ['order' => $ticket->order_number]));
        }

        if ($ticket->user_id !== null) {
            $mail->line(__('support.forwarded.account', ['url' => route('admin.users.show', $ticket->user_id)]));
        }

        return $mail
            ->line(Markdown::escape($ticket->message))
            ->action(__('support.forwarded.action'), route('admin.support'))
            ->salutation('Aucvy');
    }
}
