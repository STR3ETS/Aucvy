<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LotShare;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De ontvanger van een gedeelde kavel reageerde: interessant, niet
 * interessant of een korte tekst. Naar degene die deelde.
 */
final class ShareReaction extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public function __construct(public readonly LotShare $share) {}

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
            'kind' => 'share',
            'title' => 'Reactie op je gedeelde kavel: '.$this->share->lot->title,
            'body' => $this->body(),
            'url' => route('lots.show', $this->share->lot),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reactie op je gedeelde kavel: '.$this->share->lot->title)
            ->line('Iemand met wie je "'.Markdown::escape((string) $this->share->lot->title).'" deelde via '.$this->share->channel()->label().' reageerde:')
            ->line('"'.Markdown::escape($this->body()).'"')
            ->action('Naar de kavel', route('lots.show', $this->share->lot))
            ->salutation('Aucvy');
    }

    private function body(): string
    {
        return $this->share->reaction === LotShare::TEXT
            ? (string) $this->share->reaction_text
            : (string) $this->share->reactionLabel();
    }
}
