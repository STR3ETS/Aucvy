<?php

declare(strict_types=1);

namespace App\Live;

use Illuminate\Http\Request;

/**
 * De mediaserver achter één contract: LiveKit Cloud nu, later zelf
 * gehost, en een nepversie voor tests en lokaal. Laravel maakt per persoon
 * een token: de verkoper mag zenden, kijkers alleen ontvangen.
 */
interface LiveProvider
{
    public function name(): string;

    public function createRoom(string $room, int $maxParticipants): void;

    public function token(string $room, string $identity, string $displayName, bool $canPublish, int $ttlSeconds = 3600): string;

    public function endRoom(string $room): void;

    /**
     * De websocket-URL voor de client; null als er geen echte server is.
     */
    public function clientUrl(): ?string;

    /**
     * Leest en controleert een webhook; null als de handtekening niet klopt.
     *
     * @return array{event: string, room: string|null, participants: int|null, participant_identity: string|null}|null
     */
    public function webhook(Request $request): ?array;
}
