<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lot;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De uitkomst van de controle vóór publicatie, aan de verkoper: live,
 * terug met punten om aan te passen, of bij een medewerker.
 */
final class ListingScreened extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public const string LIVE = 'live';

    public const string RETURNED = 'returned';

    public const string ESCALATED = 'escalated';

    public function __construct(public readonly Lot $lot, public readonly string $outcome)
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
            'kind' => 'lot',
            'title' => __("notifications.listing_screened.{$this->outcome}.subject", ['lot' => $this->lot->title]),
            'body' => __("notifications.listing_screened.{$this->outcome}.line", ['lot' => $this->lot->title]),
            'url' => $this->url(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__("notifications.listing_screened.{$this->outcome}.subject", ['lot' => $this->lot->title]))
            ->line(__("notifications.listing_screened.{$this->outcome}.line", ['lot' => Markdown::escape((string) $this->lot->title)]));

        if ($this->outcome === self::RETURNED) {
            foreach ((array) ($this->lot->screening['issues'] ?? []) as $issue) {
                $mail->line('- '.Markdown::escape((string) ($issue['message'] ?? '')));
            }
        }

        return $mail
            ->action(__("notifications.listing_screened.{$this->outcome}.action"), $this->url())
            ->salutation('Aucvy');
    }

    private function url(): string
    {
        return match ($this->outcome) {
            self::LIVE => route('lots.show', $this->lot),
            self::RETURNED => route('sell', ['concept' => $this->lot->id]),
            default => route('dashboard.lots'),
        };
    }
}
