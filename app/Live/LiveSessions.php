<?php

declare(strict_types=1);

namespace App\Live;

use App\Lots\Dossier\Dossier;
use App\Models\LiveSession;
use App\Models\Lot;
use App\Models\LotActivity;
use App\Models\User;
use App\Notifications\LiveSessionNotice;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * De regels rond een uitzending: alleen een geverifieerde zakelijke
 * verkoper op een eigen, lopende kavel; eerst de check en de aftelling in
 * de studio, dan pas live; een maximum aantal kijkers per uitzending en een
 * budget per maand; valt het beeld weg, dan loopt de veiling door en krijgt
 * de verkoper direct een melding.
 */
final class LiveSessions
{
    public function __construct(private readonly LiveProvider $provider) {}

    public static function enabled(): bool
    {
        return (bool) config('aucvy.live.enabled', false);
    }

    public function canStart(Lot $lot, User $user): ?string
    {
        return match (true) {
            ! self::enabled() => 'Live uitzenden staat nog uit.',
            ! $lot->isManagedBy($user) => 'Alleen de verkoper van deze kavel kan live gaan.',
            $user->company === null || ! $user->company->isVerified() => 'Live gaan kan alleen als geverifieerde zakelijke verkoper.',
            ! $lot->status->isPublic() || $lot->auction === null || $lot->auction->isClosed() => 'De kavel moet live staan met een lopende veiling.',
            LiveSession::query()->where('lot_id', $lot->id)->open()->exists() => 'Er loopt al een uitzending voor deze kavel.',
            $this->minutesThisMonth() >= (int) config('aucvy.live.monthly_budget_minutes', 600) => 'Het maandbudget voor uitzendingen is op; neem contact op met Aucvy.',
            default => null,
        };
    }

    public function start(Lot $lot, User $user): LiveSession
    {
        $reason = $this->canStart($lot, $user);

        if ($reason !== null) {
            throw ValidationException::withMessages(['live' => $reason]);
        }

        $room = 'kavel-'.$lot->public_id.'-'.Str::lower(Str::random(6));
        $max = (int) config('aucvy.live.max_viewers', 500);

        $this->provider->createRoom($room, $max + 1);

        return LiveSession::create([
            'lot_id' => $lot->id,
            'auction_id' => $lot->auction?->id,
            'seller_id' => $user->id,
            'room' => $room,
            'provider' => $this->provider->name(),
            'status' => LiveSession::PREPARING,
            'max_viewers' => $max,
        ]);
    }

    /**
     * Na de camera- en verbindingscheck en de aftelling: live.
     */
    public function goLive(LiveSession $session): LiveSession
    {
        if ($session->status === LiveSession::PREPARING) {
            $session->forceFill(['status' => LiveSession::LIVE, 'started_at' => now()])->save();

            Dossier::record(
                lot: $session->lot_id,
                source: LotActivity::SOURCE_LIVE,
                type: 'started',
                visibility: LotActivity::PUBLIC,
                payload: ['session_id' => $session->id],
                actor: $session->seller,
                ref: "live:{$session->id}:started",
            );
        }

        return $session;
    }

    public function publisherToken(LiveSession $session, User $user): string
    {
        return $this->provider->token($session->room, 'verkoper-'.$user->id, $user->company?->name ?? $user->name, true, 4 * 3600);
    }

    /**
     * Kijken mag zonder account; bieden volgt de bestaande drempels.
     */
    public function viewerToken(LiveSession $session, ?User $user, string $guestToken): string
    {
        $identity = $user !== null ? 'kijker-'.$user->id : 'gast-'.substr(hash('sha256', $guestToken), 0, 12);

        return $this->provider->token($session->room, $identity, $user?->publicName() ?? 'Kijker', false, 2 * 3600);
    }

    public function end(LiveSession $session, string $reason = 'ended_by_seller'): LiveSession
    {
        if (! $session->isOpen()) {
            return $session;
        }

        try {
            $this->provider->endRoom($session->room);
        } catch (Throwable) {
            // De server is al weg of onbereikbaar; de administratie sluit toch.
        }

        $wasLive = $session->isLive();
        $minutes = $session->started_at !== null ? (int) ceil($session->started_at->diffInSeconds(now()) / 60) : 0;

        $session->forceFill([
            'status' => $wasLive ? LiveSession::ENDED : LiveSession::FAILED,
            'ended_at' => now(),
            'end_reason' => $reason,
            'viewers_now' => 0,
            'viewer_minutes' => $session->viewer_minutes + $minutes * max(1, $session->peak_viewers),
        ])->save();

        if ($wasLive) {
            Dossier::record(
                lot: $session->lot_id,
                source: LotActivity::SOURCE_LIVE,
                type: 'ended',
                visibility: LotActivity::PUBLIC,
                payload: ['count' => $session->peak_viewers, 'minutes' => $minutes, 'reason' => $reason],
                ref: "live:{$session->id}:ended",
                actorType: $reason === 'ended_by_seller' ? null : 'system',
            );

            if ($reason !== 'ended_by_seller') {
                // Beslissing 6: de klok loopt door, bieden blijft werken, de verkoper hoort het direct.
                $session->seller?->notify(new LiveSessionNotice($session, 'dropped'));
            }
        }

        return $session;
    }

    /**
     * Webhook van de mediaserver: kijkersaantallen en het einde van de kamer.
     *
     * @param  array{event: string, room: string|null, participants: int|null, participant_identity: string|null}  $event
     */
    public function handleWebhook(array $event): void
    {
        $session = $event['room'] !== null ? LiveSession::query()->where('room', $event['room'])->first() : null;

        if ($session === null) {
            return;
        }

        if (in_array($event['event'], ['participant_joined', 'participant_left'], true) && $event['participants'] !== null) {
            // De verkoper telt niet mee als kijker.
            $viewers = max(0, $event['participants'] - 1);
            $session->forceFill(['viewers_now' => $viewers, 'peak_viewers' => max($session->peak_viewers, $viewers)])->save();

            return;
        }

        if ($event['event'] === 'room_finished' && $session->isOpen()) {
            $this->end($session, 'room_finished');
        }

        if ($event['event'] === 'participant_left' && Str::startsWith((string) $event['participant_identity'], 'verkoper-') && $session->isLive()) {
            $session->seller?->notify(new LiveSessionNotice($session, 'dropped'));
        }
    }

    public function minutesThisMonth(): int
    {
        return (int) LiveSession::query()->where('created_at', '>=', now()->startOfMonth())->sum('viewer_minutes');
    }

    public function clientUrl(): ?string
    {
        return $this->provider->clientUrl();
    }
}
