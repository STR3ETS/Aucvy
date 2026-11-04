<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LotDistribution;
use App\Notifications\Concerns\ShowsInApp;
use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * De social push deed zijn werk: geplaatst, klaargezet om zelf te
 * plaatsen, of mislukt. Naar de verkoper, met de link naar de tab.
 */
final class DistributionUpdate extends Notification implements ShouldQueue
{
    use Queueable, ShowsInApp;

    public function __construct(public readonly LotDistribution $distribution) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->distribution->status === LotDistribution::POSTED ? ['database'] : ['database', 'mail'];
    }

    protected function inApp(object $notifiable): array
    {
        return [
            'kind' => 'distribution',
            'title' => $this->title(),
            'body' => $this->line(),
            'url' => route('dashboard.lots.show', [$this->distribution->lot, 'tab' => 'verspreiding']),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line(Markdown::escape($this->line()))
            ->action('Naar de verspreiding', route('dashboard.lots.show', [$this->distribution->lot, 'tab' => 'verspreiding']))
            ->salutation('Aucvy');
    }

    private function title(): string
    {
        $channel = $this->distribution->channelLabel();
        $lot = (string) $this->distribution->lot->title;

        return match ($this->distribution->status) {
            LotDistribution::POSTED => "Geplaatst op {$channel}: {$lot}",
            LotDistribution::READY => "Je bericht voor {$channel} staat klaar: {$lot}",
            default => "Plaatsen op {$channel} is mislukt: {$lot}",
        };
    }

    private function line(): string
    {
        return match ($this->distribution->status) {
            LotDistribution::POSTED => 'Het bericht voor "'.$this->distribution->momentLabel().'" staat online. De link toont altijd het actuele bod.',
            LotDistribution::READY => $this->distribution->channelLabel().' is niet gekoppeld, dus plaats je het zelf: tekst en beeld staan klaar op de tab Verspreiding. Vink daarna "Ik heb dit geplaatst" aan.',
            default => 'Het kanaal gaf een fout: '.($this->distribution->last_error ?? 'onbekend').'. Plaats het bericht zelf via de deelkit of koppel het kanaal opnieuw.',
        };
    }
}
