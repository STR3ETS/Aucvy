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
 *
 * Het token is vrij te kiezen, dus de teller is ook vrij op te blazen. Twee
 * remmen: de route is per IP begrensd, en per IP tellen hooguit een handvol
 * tabbladen mee. Zo blijft "40 kijken nu" iets wat je niet vanaf één
 * verbinding kunt maken.
 */
final class ViewerPresence
{
    /**
     * Twee gemiste hartslagen en je telt niet meer mee.
     */
    private const TTL_SECONDS = 90;

    /**
     * Meer tabbladen dan dit vanaf één adres tellen niet mee. Een kantoor
     * achter één IP ziet zichzelf dan iets te laag, nooit te hoog.
     */
    public const MAX_PER_IP = 5;

    public function beat(int $auctionId, string $token, ?string $ip = null): int
    {
        $lock = Cache::lock($this->key($auctionId).':lock', 2);

        try {
            // Twee hartslagen tegelijk lezen anders dezelfde lijst en de
            // tweede schrijft de eerste weg.
            $lock->block(2);

            $viewers = $this->fresh($auctionId);
            $viewers[$token] = ['seen' => now()->getTimestamp(), 'ip' => $ip === null ? null : hash('xxh128', $ip)];

            Cache::put($this->key($auctionId), $viewers, self::TTL_SECONDS);
        } finally {
            $lock->release();
        }

        return $this->countOf($viewers);
    }

    public function count(int $auctionId): int
    {
        return $this->countOf($this->fresh($auctionId));
    }

    /**
     * @param  array<string, array{seen: int, ip: string|null}>  $viewers
     */
    private function countOf(array $viewers): int
    {
        $perIp = [];
        $count = 0;

        foreach ($viewers as $viewer) {
            $ip = $viewer['ip'];

            if ($ip === null) {
                $count++;

                continue;
            }

            $perIp[$ip] = ($perIp[$ip] ?? 0) + 1;

            if ($perIp[$ip] <= self::MAX_PER_IP) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, array{seen: int, ip: string|null}>
     */
    private function fresh(int $auctionId): array
    {
        $cutoff = now()->getTimestamp() - self::TTL_SECONDS;
        $viewers = [];

        foreach (Cache::get($this->key($auctionId), []) as $token => $viewer) {
            // Oude vorm (alleen een tijdstip) blijft meetellen tot hij verloopt.
            $entry = is_array($viewer) ? $viewer : ['seen' => (int) $viewer, 'ip' => null];

            if ($entry['seen'] > $cutoff) {
                $viewers[$token] = $entry;
            }
        }

        return $viewers;
    }

    private function key(int $auctionId): string
    {
        return "auction:{$auctionId}:viewers";
    }
}
