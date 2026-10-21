<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Eén mail naar support als een systeemcontrole faalt. Niet in de
 * wachtrij: de wachtrij kan zelf het probleem zijn.
 */
final class SystemAlert extends Notification
{
    /**
     * @param  list<array{key: string, label: string, status: string, value: string, hint: string|null}>  $failing
     */
    public function __construct(public readonly array $failing) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Aucvy: '.count($this->failing).' '.(count($this->failing) === 1 ? 'systeemcontrole faalt' : 'systeemcontroles falen'))
            ->line('De systeemcontrole van '.now()->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM, HH:mm').' vond:');

        foreach ($this->failing as $check) {
            $mail->line('- '.$check['label'].': '.$check['value'].($check['hint'] !== null ? ' ('.$check['hint'].')' : ''));
        }

        return $mail
            ->action('Naar de systeempagina', route('admin.system'))
            ->line('Deze melding komt hooguit één keer per uur per controle.')
            ->salutation('Aucvy');
    }
}
