<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\KycStatus;
use App\Notifications\Concerns\ShowsInApp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De uitkomst van de identiteitscontrole (kyc) of de bedrijfscontrole
 * (kyb). Bij afwijzing staat de reden erbij, zodat de ontvanger weet wat
 * er nodig is om het opnieuw te proberen.
 */
final class VerificationDecided extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    /**
     * @param  'kyc'|'kyb'  $kind
     */
    public function __construct(
        public readonly KycStatus $status,
        public readonly ?string $reason = null,
        public readonly string $kind = 'kyc',
    ) {
        $this->afterCommit = true;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    protected function inApp(object $notifiable): array
    {
        $approved = $this->status === KycStatus::Verified;
        $key = 'notifications.verification.'.$this->kind.'_'.($approved ? 'approved' : 'rejected');

        return [
            'kind' => 'verification',
            'title' => __($key.'_subject'),
            'body' => $approved || $this->reason === null ? __($key.'_line') : __('notifications.verification.reason', ['reason' => $this->reason]),
            'url' => $approved ? route('lots.index') : route('verification.overview'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->status === KycStatus::Verified;
        $key = 'notifications.verification.'.$this->kind.'_'.($approved ? 'approved' : 'rejected');

        $mail = (new MailMessage)
            ->subject(__($key.'_subject'))
            ->line(__($key.'_line'));

        if (! $approved && $this->reason !== null) {
            $mail->line(__('notifications.verification.reason', ['reason' => $this->reason]));
        }

        return $mail
            ->action(
                __($approved ? 'notifications.verification.approved_action' : 'notifications.verification.rejected_action'),
                $approved ? route('lots.index') : route('verification.overview'),
            )
            ->salutation('Aucvy');
    }
}
