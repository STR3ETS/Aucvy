<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Per soort melding en per kanaal aan of uit, met stille uren. De
 * service-eisen (betaalverzoek, geschil) staan vast in code.
 */
#[Fillable(['user_id', 'alert_key', 'channel', 'enabled', 'quiet_start', 'quiet_end'])]
class NotificationPreference extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public static function allows(User $user, string $alertKey, string $channel): bool
    {
        $row = self::query()->where('user_id', $user->id)->where('alert_key', $alertKey)->where('channel', $channel)->first()
            ?? self::query()->where('user_id', $user->id)->where('alert_key', '*')->where('channel', $channel)->first();

        return $row === null ? true : $row->enabled;
    }
}
