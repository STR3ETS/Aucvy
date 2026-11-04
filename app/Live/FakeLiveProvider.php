<?php

declare(strict_types=1);

namespace App\Live;

use Illuminate\Http\Request;

/**
 * Geen mediaserver: lokaal en in tests. Onthoudt wat er gevraagd is en
 * geeft herkenbare tokens terug; de webhook neemt een gedeelde sleutel.
 */
final class FakeLiveProvider implements LiveProvider
{
    /** @var list<array{method: string, room: string}> */
    public array $calls = [];

    public function name(): string
    {
        return 'fake';
    }

    public function createRoom(string $room, int $maxParticipants): void
    {
        $this->calls[] = ['method' => 'createRoom', 'room' => $room];
    }

    public function token(string $room, string $identity, string $displayName, bool $canPublish, int $ttlSeconds = 3600): string
    {
        return 'fake.'.$room.'.'.$identity.'.'.($canPublish ? 'publish' : 'view');
    }

    public function endRoom(string $room): void
    {
        $this->calls[] = ['method' => 'endRoom', 'room' => $room];
    }

    public function clientUrl(): ?string
    {
        return null;
    }

    public function webhook(Request $request): ?array
    {
        if ($request->header('X-Live-Secret') !== (string) config('aucvy.live.fake_webhook_secret', 'fake')) {
            return null;
        }

        return [
            'event' => (string) $request->input('event', ''),
            'room' => $request->input('room'),
            'participants' => $request->has('participants') ? (int) $request->input('participants') : null,
            'participant_identity' => $request->input('participant_identity'),
        ];
    }
}
