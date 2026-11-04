<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een voorstel van Ivy dat niets doet tot de klant bevestigt: bod,
 * autobod, afmijnen, koop direct, reserve omlaag, publiceren. Statusmachine
 * proposed, shown, confirmed, executed; vanuit shown ook price_changed
 * (terug naar shown), expired, rejected of cancelled.
 */
#[Fillable([
    'conversation_id', 'user_id', 'type', 'lot_id', 'payload', 'amount_cents', 'expected_current_cents', 'expected_ends_at',
    'status', 'expires_at', 'confirmed_at', 'executed_at', 'result', 'idempotency_key', 'confirmed_ip',
])]
class IvyActionProposal extends Model
{
    use HasUlids;

    public const string BID = 'bid';

    public const string AUTO_BID = 'auto_bid';

    public const string DUTCH_ACCEPT = 'dutch_accept';

    public const string BUY_NOW = 'buy_now';

    public const string RESERVE_CHANGE = 'reserve_change';

    public const string PUBLISH = 'publish';

    public const TYPES = [self::BID, self::AUTO_BID, self::DUTCH_ACCEPT, self::BUY_NOW, self::RESERVE_CHANGE, self::PUBLISH];

    public const string PROPOSED = 'proposed';

    public const string SHOWN = 'shown';

    public const string CONFIRMED = 'confirmed';

    public const string EXECUTED = 'executed';

    public const string PRICE_CHANGED = 'price_changed';

    public const string EXPIRED = 'expired';

    public const string REJECTED = 'rejected';

    public const string CANCELLED = 'cancelled';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'amount_cents' => 'integer',
            'expected_current_cents' => 'integer',
            'expected_ends_at' => 'datetime',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(IvyConversation::class, 'conversation_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PROPOSED, self::SHOWN, self::PRICE_CHANGED], true);
    }

    public function typeLabel(): string
    {
        return __('ivy.proposals.'.$this->type);
    }
}
