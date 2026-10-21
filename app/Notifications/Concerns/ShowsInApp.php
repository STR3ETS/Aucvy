<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

/**
 * Het meldingencentrum (briefing sectie 3): elke melding die per mail of
 * push gaat, staat ook in-app. Eén vorm voor alle soorten: een soort, een
 * kop, een regel en waar je heen kunt.
 */
trait ShowsInApp
{
    /**
     * @return array{kind: string, title: string, body: string, url: string|null}
     */
    abstract protected function inApp(object $notifiable): array;

    /**
     * @return array{kind: string, title: string, body: string, url: string|null}
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->inApp($notifiable);
    }
}
