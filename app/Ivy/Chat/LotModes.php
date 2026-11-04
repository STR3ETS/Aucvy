<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use App\Models\IvyLotMode;
use App\Models\Lot;
use App\Models\User;
use App\Support\Audit\Audit;

/**
 * Het niveau per kavel: alleen melden, eerst vragen, automatisch tot
 * limiet. Automatisch alleen via een bevestigd voorstel; de tekstversie
 * van het mandaat wordt vastgelegd.
 */
final class LotModes
{
    public const string CONSENT_VERSION = 'autobod-v1';

    public function set(User $user, Lot $lot, string $mode, ?int $limitCents = null, ?string $ip = null): IvyLotMode
    {
        $row = IvyLotMode::query()->updateOrCreate(
            ['user_id' => $user->id, 'lot_id' => $lot->id],
            [
                'mode' => $mode,
                'limit_cents' => $mode === IvyLotMode::AUTO ? $limitCents : null,
                'consent_text_version' => $mode === IvyLotMode::AUTO ? self::CONSENT_VERSION : null,
                'consented_at' => $mode === IvyLotMode::AUTO ? now() : null,
                'ended_at' => null,
            ],
        );

        Audit::record('ivy.lot_mode', $lot, $user, after: ['mode' => $mode, 'limit_cents' => $row->limit_cents, 'consent' => $row->consent_text_version], ip: $ip);

        return $row;
    }

    public function stop(User $user, Lot $lot): void
    {
        IvyLotMode::query()->where('user_id', $user->id)->where('lot_id', $lot->id)->update(['mode' => IvyLotMode::NOTIFY, 'ended_at' => now()]);
    }
}
