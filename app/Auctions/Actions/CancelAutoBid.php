<?php

declare(strict_types=1);

namespace App\Auctions\Actions;

use App\Models\Auction;
use App\Models\AutoBid;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Haalt een maximum weg.
 *
 * Het reeds geplaatste bod blijft gewoon staan - je trekt je plafond in, niet
 * je bod. De UI moet dat ook zeggen, want de verwachting is vaak het
 * tegenovergestelde (hfst. 7).
 *
 * De rij wordt niet verwijderd maar op inactief gezet: de historie van wat
 * iemand ooit bereid was te betalen blijft daarmee bewaard, terwijl de
 * gegenereerde `active_flag` de unieke index vrijgeeft voor een nieuw maximum.
 */
final class CancelAutoBid
{
    public function handle(Auction $auction, User $user): ?AutoBid
    {
        return DB::transaction(function () use ($auction, $user): ?AutoBid {
            $autoBid = AutoBid::query()
                ->where('auction_id', $auction->id)
                ->where('user_id', $user->id)
                ->where('active', true)
                ->lockForUpdate()
                ->first();

            if ($autoBid === null) {
                return null;
            }

            $autoBid->active = false;
            $autoBid->cancelled_at = now();
            $autoBid->save();

            return $autoBid;
        });
    }
}
