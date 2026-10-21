<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Eén mail naar support als er jobs mislukten. Bewust niet in de wachtrij:
 * als de wachtrij zelf het probleem is, moet dit bericht er toch uit.
 */
final class FailedJobsAlert extends Notification
{
    /**
     * @param  list<array{job: string, total: int}>  $byJob
     */
    public function __construct(
        public readonly array $byJob,
        public readonly int $totalFailed,
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
        $new = array_sum(array_column($this->byJob, 'total'));

        $mail = (new MailMessage)
            ->subject('Aucvy: '.$new.' '.($new === 1 ? 'job mislukt' : 'jobs mislukt'))
            ->line('Sinds de vorige controle '.($new === 1 ? 'is er één job mislukt' : 'zijn er '.$new.' jobs mislukt').'. In totaal staan er '.$this->totalFailed.' in de dead-letter-bak.');

        foreach ($this->byJob as $row) {
            $mail->line('- '.class_basename($row['job']).': '.$row['total']);
        }

        return $mail
            ->action('Naar de wachtrij', route('admin.queue'))
            ->line('Opnieuw proberen of opruimen kan daar; de volledige fout staat in het logbestand.')
            ->salutation('Aucvy');
    }
}
