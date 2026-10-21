<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Auctions\Events\Outbid;
use App\Notifications\OutbidMail;
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
        // Zonder ingeschreven apparaat, of als push uitstaat, valt het bericht
        // terug op mail (als die aanstaat) en in-app; trager, maar de bieder
        // hoort het wél (briefing sectie 8).
        if (! $event->user->prefers('outbid_push') || $event->user->pushSubscriptions()->count() === 0) {
            $event->user->notify(new OutbidMail($event));

            return;
        }

        $event->user->notify(new OutbidPush($event));
    }
}
