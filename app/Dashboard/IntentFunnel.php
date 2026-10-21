<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Enums\UserType;
use App\Models\Bid;
use App\Models\User;
use App\Models\Viewing;
use App\Models\WatchlistItem;

/**
 * De koperintentie-trechter (hfst. 13.5): gedragssignalen, geen
 * persoonsgegevens. Alleen aantallen - dat staat er op de pagina letterlijk
 * bij en dat moet hier waar blijven: dit object kent geen namen.
 *
 * Eerlijk over de bron: de trechter telt volglijsten, kijkdagaanvragen en
 * bieders op de kavels van deze verkoper. Bezoekersaantallen vergen
 * paginametingen die er nog niet zijn - die regel ontbreekt dus bewust in
 * plaats van dat er een verzonnen getal staat.
 */
final class IntentFunnel
{
    /**
     * @return array{watchers: int, viewings: int, bidders: int, business: int}
     */
    public function forSeller(User $user): array
    {
        // Als subquery, niet als lijst: een bedrijf met duizenden kavels
        // stuurt anders duizenden id's heen en weer per telling.
        $lotIds = $user->lots()->select('id');

        $bidderIds = Bid::query()
            ->whereHas('auction', fn ($query) => $query->whereIn('lot_id', $lotIds))
            ->distinct()
            ->select('user_id');

        return [
            'watchers' => WatchlistItem::query()->whereIn('lot_id', $lotIds)->distinct()->count('user_id'),
            'viewings' => Viewing::query()->whereIn('lot_id', $lotIds)->count(),
            'bidders' => (clone $bidderIds)->count('user_id'),
            'business' => User::query()
                ->whereIn('id', $bidderIds)
                ->where('type', UserType::Business)
                ->count(),
        ];
    }
}
