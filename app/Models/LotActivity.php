<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén regel in het kaveldossier. Zie App\Lots\Dossier\Dossier voor het
 * vastleggen en de zichtbaarheidsregels.
 */
#[Fillable([
    'lot_id', 'order_id', 'source', 'type', 'actor_type', 'actor_id',
    'visibility', 'user_id', 'payload', 'source_ref', 'occurred_at', 'created_at',
])]
class LotActivity extends Model
{
    public const null UPDATED_AT = null;

    // Bronnen, in de volgorde van de briefing.
    public const string SOURCE_LOT = 'lot';

    public const string SOURCE_AUCTION = 'auction';

    public const string SOURCE_ORDER = 'order';

    public const string SOURCE_MESSAGE = 'message';

    public const string SOURCE_MAIL = 'mail';

    public const string SOURCE_DOCUMENT = 'document';

    public const string SOURCE_DISTRIBUTION = 'distribution';

    public const string SOURCE_LIVE = 'live';

    /** @var list<string> */
    public const SOURCES = [
        self::SOURCE_LOT, self::SOURCE_AUCTION, self::SOURCE_ORDER, self::SOURCE_MESSAGE,
        self::SOURCE_MAIL, self::SOURCE_DOCUMENT, self::SOURCE_DISTRIBUTION, self::SOURCE_LIVE,
    ];

    // Zichtbaarheid.
    public const string PUBLIC = 'public';

    public const string USER = 'user';

    public const string BUYER = 'buyer';

    public const string SELLER = 'seller';

    public const string PARTIES = 'parties';

    public const string STAFF = 'staff';

    /** Een gesprek: de koper in het gesprek (user_id), de verkoper en beheer. */
    public const string CONVERSATION = 'conversation';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    /**
     * Wat deze lezer mag zien. Een koper ziet nooit biedingen of gesprekken
     * van anderen; een regel met visibility user is alleen voor die ene
     * gebruiker en voor beheer.
     *
     * @param  Builder<LotActivity>  $query
     * @param  'seller'|'buyer'|'staff'|'public'  $role
     */
    public function scopeVisibleTo(Builder $query, string $role, ?User $user = null): void
    {
        if ($role === 'staff') {
            return;
        }

        $levels = match ($role) {
            'seller' => [self::PUBLIC, self::SELLER, self::PARTIES, self::CONVERSATION],
            'buyer' => [self::PUBLIC, self::BUYER, self::PARTIES],
            default => [self::PUBLIC],
        };

        $query->where(function (Builder $query) use ($levels, $user): void {
            $query->whereIn('visibility', $levels);

            if ($user !== null) {
                $query->orWhere(fn (Builder $query) => $query->whereIn('visibility', [self::USER, self::CONVERSATION])->where('user_id', $user->id));
            }
        });
    }

    public function label(): string
    {
        $key = "dossier.types.{$this->source}.{$this->type}";

        return __($key) === $key ? "{$this->source}: {$this->type}" : __($key, $this->replacements());
    }

    /**
     * @return array<string, string>
     */
    private function replacements(): array
    {
        $payload = $this->payload ?? [];

        return [
            'amount' => isset($payload['amount_cents']) ? Money::format((int) $payload['amount_cents']) : '',
            'status' => (string) ($payload['status'] ?? ''),
            'channel' => (string) ($payload['channel'] ?? ''),
            'kind' => (string) ($payload['kind'] ?? ''),
            'subject' => (string) ($payload['subject'] ?? ''),
            'count' => (string) ($payload['count'] ?? ''),
        ];
    }
}
