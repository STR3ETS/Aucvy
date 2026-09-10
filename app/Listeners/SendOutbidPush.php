<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Auctions\Events\Outbid;
use App\Notifications\OutbidPush;

/**
 * Koppelt het Outbid-event aan de web push. De listener zelf is synchroon
 * (hij zet alleen een notificatie klaar); de notificatie staat op de
 * realtime-queue en verstuurt pas na de commit.
 */
final class SendOutbidPush
{
    public function handle(Outbid $event): void
    {
        if ($event->user->pushSubscriptions()->count() === 0) {
            return;
        }

        $event->user->notify(new OutbidPush($event));
    }
}
