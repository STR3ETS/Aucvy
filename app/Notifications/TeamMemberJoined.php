<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Een collega heeft de uitnodiging aangenomen: de uitnodiger (of anders de
 * eigenaren) hoort dat, in-app en per mail.
 */
final class TeamMemberJoined extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public function __construct(public readonly User $member, public readonly string $roleLabel)
    {
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
        return [
            'kind' => 'team',
            'title' => __('notifications.team_joined.subject', ['name' => $this->member->name]),
            'body' => __('notifications.team_joined.line', ['name' => $this->member->name, 'role' => $this->roleLabel]),
            'url' => route('team.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.team_joined.subject', ['name' => $this->member->name]))
            ->line(__('notifications.team_joined.line', ['name' => Markdown::escape($this->member->name), 'role' => $this->roleLabel]))
            ->action(__('notifications.team_joined.action'), route('team.index'))
            ->salutation('Aucvy');
    }
}
