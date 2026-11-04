<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hoeveel Ivy per kavel mag doen: alleen melden (standaard), eerst vragen,
 * of automatisch tot een limiet (opt-in via een bevestigd voorstel, loopt
 * via het bestaande autobod).
 */
#[Fillable(['user_id', 'lot_id', 'mode', 'limit_cents', 'consent_text_version', 'consented_at', 'ended_at'])]
class IvyLotMode extends Model
{
    public const string NOTIFY = 'notify';

    public const string ASK = 'ask';

    public const string AUTO = 'auto';

    public const MODES = [self::NOTIFY, self::ASK, self::AUTO];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['limit_cents' => 'integer', 'consented_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function for(User $user, Lot $lot): string
    {
        return (string) (self::query()->where('user_id', $user->id)->where('lot_id', $lot->id)->whereNull('ended_at')->value('mode') ?? self::NOTIFY);
    }

    public function modeLabel(): string
    {
        return __('ivy.modes.'.$this->mode);
    }
}
