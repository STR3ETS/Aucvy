<?php

declare(strict_types=1);

namespace App\Live;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * LiveKit: toegangstokens zijn JWT's (HS256) met een video-grant, de
 * server-API loopt via Twirp over https, en webhooks dragen een JWT met de
 * sha256 van de body. Zonder extra pakket; de sleutels staan in services.
 */
final class LiveKitProvider implements LiveProvider
{
    public function __construct(
        private readonly string $host,
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly ?string $clientUrl = null,
    ) {}

    public function name(): string
    {
        return 'livekit';
    }

    public function createRoom(string $room, int $maxParticipants): void
    {
        $this->call('RoomService/CreateRoom', ['name' => $room, 'max_participants' => $maxParticipants, 'empty_timeout' => 600], ['roomCreate' => true]);
    }

    public function token(string $room, string $identity, string $displayName, bool $canPublish, int $ttlSeconds = 3600): string
    {
        return $this->jwt([
            'sub' => $identity,
            'name' => $displayName,
            'video' => [
                'room' => $room,
                'roomJoin' => true,
                'canPublish' => $canPublish,
                'canPublishData' => $canPublish,
                'canSubscribe' => true,
            ],
        ], $ttlSeconds);
    }

    public function endRoom(string $room): void
    {
        $this->call('RoomService/DeleteRoom', ['room' => $room], ['roomCreate' => true]);
    }

    public function clientUrl(): ?string
    {
        return $this->clientUrl ?: null;
    }

    public function webhook(Request $request): ?array
    {
        $token = trim((string) $request->header('Authorization', ''));
        $claims = $this->verifyJwt($token);

        if ($claims === null) {
            return null;
        }

        $body = (string) $request->getContent();

        if (! hash_equals((string) ($claims['sha256'] ?? ''), base64_encode(hash('sha256', $body, true)))) {
            return null;
        }

        $data = (array) json_decode($body, true);

        return [
            'event' => (string) ($data['event'] ?? ''),
            'room' => $data['room']['name'] ?? null,
            'participants' => isset($data['room']['numParticipants']) ? (int) $data['room']['numParticipants'] : null,
            'participant_identity' => $data['participant']['identity'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, bool>  $grant
     */
    private function call(string $method, array $payload, array $grant): void
    {
        $token = $this->jwt(['video' => $grant], 300);

        Http::timeout(15)->withToken($token)->asJson()->post(rtrim($this->host, '/').'/twirp/livekit.'.$method, $payload)->throw();
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function jwt(array $claims, int $ttlSeconds): string
    {
        $now = now()->getTimestamp();
        $header = $this->encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $body = $this->encode($claims + ['iss' => $this->apiKey, 'nbf' => $now, 'exp' => $now + $ttlSeconds, 'jti' => Str::random(12)]);
        $signature = $this->encodeRaw(hash_hmac('sha256', $header.'.'.$body, $this->apiSecret, true));

        return $header.'.'.$body.'.'.$signature;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function verifyJwt(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $signature] = $parts;
        $expected = $this->encodeRaw(hash_hmac('sha256', $header.'.'.$body, $this->apiSecret, true));

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        $claims = json_decode((string) base64_decode(strtr($body, '-_', '+/'), true), true);

        if (! is_array($claims) || ($claims['iss'] ?? null) !== $this->apiKey || (isset($claims['exp']) && (int) $claims['exp'] < now()->getTimestamp())) {
            return null;
        }

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function encode(array $data): string
    {
        return $this->encodeRaw((string) json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    private function encodeRaw(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
