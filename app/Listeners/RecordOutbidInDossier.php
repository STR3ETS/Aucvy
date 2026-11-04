<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Auctions\Events\Outbid;
use App\Lots\Dossier\Dossier;
use App\Models\LotActivity;

/**
 * "Overboden" in het dossier, alleen zichtbaar voor wie overboden is: een
 * andere koper ziet nooit wie er nog meer bood.
 */
final class RecordOutbidInDossier
{
    public function handle(Outbid $event): void
    {
        Dossier::record(
            lot: $event->auction->lot_id,
            source: LotActivity::SOURCE_AUCTION,
            type: 'outbid',
            visibility: LotActivity::USER,
            payload: ['amount_cents' => $event->newPriceCents, 'is_automatic' => $event->byAutomaticBid],
            userId: $event->user->id,
        );
    }
}
