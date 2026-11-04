<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een gesprek hoort bij precies één kavel en één koper-verkoper-paar. Twee
 * vormen: een korte vraag bij een onderdeel van de kavel, en een doorlopend
 * gesprek. Een derde vorm, live, hoort bij een uitzending.
 */
#[Fillable([
    'lot_id', 'order_id', 'buyer_id', 'seller_id', 'company_id', 'kind', 'status', 'lot_part',
    'last_message_at', 'buyer_read_at', 'seller_read_at', 'flag', 'flagged_at',
    'reported_by', 'reported_at', 'report_note', 'blocked_by', 'blocked_at',
    'operator_id', 'operator_joined_at', 'dispute_id',
])]
class Conversation extends Model
{
    public const string KIND_QUESTION = 'question';

    public const string KIND_CHAT = 'chat';

    public const string KIND_LIVE = 'live';

    public const string STATUS_OPEN = 'open';

    public const string STATUS_CLOSED = 'closed';

    /** @var list<string> Waar een korte vraag over kan gaan. */
    public const PARTS = ['specificaties', 'staat', 'documenten', 'ophalen', 'betaling', 'foto'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'buyer_read_at' => 'datetime',
            'seller_read_at' => 'datetime',
            'flagged_at' => 'datetime',
            'reported_at' => 'datetime',
            'blocked_at' => 'datetime',
            'operator_joined_at' => 'datetime',
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

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id')->withTrashed();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id')->withTrashed();
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('id');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ConversationAccessLog::class);
    }

    /**
     * Alles waar deze gebruiker partij in is, als koper of als verkoper
     * (inclusief collega's met rechten op de kavel).
     *
     * @param  Builder<Conversation>  $query
     */
    public function scopeForParticipant(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->where('buyer_id', $user->id)
                ->orWhereIn('seller_id', $user->lotSellerIds());
        });
    }

    public function isParticipant(User $user): bool
    {
        return $user->id === $this->buyer_id || in_array($this->seller_id, $user->lotSellerIds(), true);
    }

    public function isBuyer(User $user): bool
    {
        return $user->id === $this->buyer_id;
    }

    /**
     * Hoe de ander heet voor deze lezer: de koper is zijn biednaam, de
     * verkoper zijn bedrijfsnaam. Nooit de echte naam van de koper.
     */
    public function counterpartLabel(User $viewer): string
    {
        return $this->isBuyer($viewer) ? $this->sellerLabel() : $this->buyerLabel();
    }

    public function buyerLabel(): string
    {
        return $this->buyer?->publicName() ?? 'Koper';
    }

    public function sellerLabel(): string
    {
        return $this->company?->name ?? $this->seller?->publicName() ?? 'Verkoper';
    }

    public function kindLabel(): string
    {
        return __('messaging.kinds.'.$this->kind);
    }

    public function partLabel(): ?string
    {
        return $this->lot_part === null ? null : __('messaging.parts.'.$this->lot_part);
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function hasOperator(): bool
    {
        return $this->operator_joined_at !== null;
    }

    public function needsAttention(): bool
    {
        return $this->flag !== null;
    }

    /**
     * Ongelezen voor deze lezer: er is een bericht na zijn laatste bezoek,
     * niet van hemzelf.
     */
    public function isUnreadFor(User $user): bool
    {
        $readAt = $this->isBuyer($user) ? $this->buyer_read_at : $this->seller_read_at;

        return $this->last_message_at !== null && ($readAt === null || $this->last_message_at->greaterThan($readAt));
    }

    public function markReadFor(User $user): void
    {
        $this->forceFill([$this->isBuyer($user) ? 'buyer_read_at' : 'seller_read_at' => now()])->save();
    }
}
