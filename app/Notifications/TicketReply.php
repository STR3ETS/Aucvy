<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Het antwoord van een medewerker op een ticket, als mail in dezelfde
 * draad: het ticketnummer in het onderwerp, en antwoorden komt via de
 * Mailgun-route weer in het ticket.
 */
final class TicketReply extends Notification
{
    use Queueable;

    public function __construct(public readonly SupportTicket $ticket, public readonly TicketMessage $message) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Re: {$this->ticket->subject} [{$this->ticket->number}]")
            ->replyTo((string) config('aucvy.support.inbound_address'), 'Aucvy klantenservice')
            ->greeting('Hallo '.Markdown::escape($this->ticket->name).',')
            ->line(Markdown::escape($this->message->body))
            ->line('Je kunt op deze mail antwoorden; je antwoord komt bij hetzelfde ticket.')
            ->salutation('Aucvy klantenservice');
    }
}
