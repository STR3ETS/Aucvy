<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Geld dat een mens moet bekijken: een betaling na annulering, een borg
 * die ingehouden hoort te worden, een tweede betaling. Zolang de
 * betaalprovider geen terugboekingen doet, is support de volgende stap.
 * Niet in de wachtrij: dit moet aankomen.
 */
final class MoneyNeedsAttention extends Notification
{
    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly string $subject,
        public readonly array $lines,
        public readonly string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Aucvy: '.$this->subject);

        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail
            ->action('Bekijk in de backoffice', $this->url)
            ->line('Er is nog geen automatische terugboeking; handel dit af bij de betaalprovider en noteer het in het auditlog.')
            ->salutation('Aucvy');
    }
}
