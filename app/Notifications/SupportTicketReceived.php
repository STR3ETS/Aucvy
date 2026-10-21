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
 * De ontvangstbevestiging aan wie het contactformulier invulde.
 */
final class SupportTicketReceived extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject(__('support.received.subject', ['id' => $this->ticket->id]))
            ->greeting(__('support.received.greeting', ['name' => Markdown::escape($this->ticket->name)]))
            ->line(__('support.received.line', ['subject' => Markdown::escape($this->ticket->subject)]))
            ->line(__('support.received.quote', ['message' => Markdown::escape($this->ticket->message)]))
            ->line(__('support.received.next'))
            ->salutation('Aucvy');
    }
}
