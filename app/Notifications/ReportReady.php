<?php

declare(strict_types=1);

namespace App\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Een rapport uit het control center staat klaar; de link verloopt na een dag.
 */
final class ReportReady extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $reportKey,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
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
        $title = __('admin.reports.'.$this->reportKey);

        return (new MailMessage)
            ->subject("Rapport: {$title} ({$this->from->isoFormat('D MMM')} tot {$this->to->isoFormat('D MMM YYYY')})")
            ->line("Het rapport \"{$title}\" over {$this->from->isoFormat('D MMMM')} tot en met {$this->to->isoFormat('D MMMM YYYY')} staat klaar.")
            ->action('Download het rapport (csv)', $this->url)
            ->line('De link werkt 24 uur. Daarna maak je in het control center een nieuwe.')
            ->salutation('Aucvy');
    }
}
