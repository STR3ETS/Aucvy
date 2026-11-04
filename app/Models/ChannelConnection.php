<?php

declare(strict_types=1);

namespace App\Models;

use App\Distribution\Channel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een gekoppeld kanaal van een verkoper. De tokens staan versleuteld; de
 * status is zichtbaar en "Opnieuw koppelen" is altijd mogelijk.
 */
#[Fillable(['user_id', 'company_id', 'channel', 'tokens', 'external_id', 'external_name', 'expires_at', 'status', 'last_error'])]
class ChannelConnection extends Model
{
    public const string PENDING = 'pending';

    public const string CONNECTED = 'connected';

    public const string EXPIRED = 'expired';

    public const string ERROR = 'error';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tokens' => 'encrypted:array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): Channel
    {
        return Channel::from($this->channel);
    }

    public function isUsable(): bool
    {
        return $this->status === self::CONNECTED && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function token(string $key = 'access_token'): ?string
    {
        $value = $this->tokens[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function statusLabel(): string
    {
        return __('distribution.connection_status.'.($this->status === self::CONNECTED && ! $this->isUsable() ? self::EXPIRED : $this->status));
    }
}
