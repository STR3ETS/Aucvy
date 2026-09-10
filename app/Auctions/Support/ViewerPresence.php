<?php

declare(strict_types=1);

namespace App\Auctions\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Wie kijkt er nu naar deze veiling?
 *
 * Geen presence-kanaal maar een hartslag op de cache: elke open kavelpagina
 * meldt zich elke dertig seconden met een willekeurig tabblad-token. Zo tellen
 * gasten gewoon mee en blijft de teller werken als de websocket wegvalt.
 * Er gaat nooit méér dan een aantal naar buiten - geen namen (hfst. 7).
 */
final class ViewerPresence
{
    /**
     * Twee gemiste hartslagen en je telt niet meer mee.
     */
    private const TTL_SECONDS = 90;

    public function beat(int $auctionId, string $token): int
    {
        $viewers = $this->fresh($auctionId);
        $viewers[$token] = now()->getTimestamp();

        Cache::put($this->key($auctionId), $viewers, self::TTL_SECONDS);

        return count($viewers);
    }

    public function count(int $auctionId): int
    {
        return count($this->fresh($auctionId));
    }

    /**
     * @return array<string, int>
     */
    private function fresh(int $auctionId): array
    {
        $cutoff = now()->getTimestamp() - self::TTL_SECONDS;

        return array_filter(
            Cache::get($this->key($auctionId), []),
            fn (int $lastSeen): bool => $lastSeen > $cutoff,
        );
    }

    private function key(int $auctionId): string
    {
        return "auction:{$auctionId}:viewers";
    }
}
