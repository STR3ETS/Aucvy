<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CompanyInvitation;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De uitnodiging om bij een bedrijfsaccount te komen. Gaat naar een
 * e-mailadres, niet naar een gebruiker: de ontvanger heeft misschien nog
 * geen account.
 */
final class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly CompanyInvitation $invitation)
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
        $invitation = $this->invitation;
        $company = $invitation->company;
        $inviter = $invitation->inviter?->name ?? 'Een collega';

        return (new MailMessage)
            ->subject(__('team.mail.subject', ['company' => $company->name]))
            ->greeting(__('team.mail.greeting'))
            ->line(__('team.mail.line', ['inviter' => Markdown::escape($inviter), 'company' => Markdown::escape($company->name), 'role' => $invitation->role->label()]))
            ->line($invitation->role->description())
            ->action(__('team.mail.action'), route('team.invitation', $invitation))
            ->line(__('team.mail.account', ['email' => $invitation->email]))
            ->line(__('team.mail.expires', ['days' => CompanyInvitation::VALID_DAYS]))
            ->salutation('Aucvy');
    }
}
