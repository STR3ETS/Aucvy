<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een alert is een regel op een domeinevent met een vaste sjabloontekst
 * en twee of drie keuzes, altijd met een uitweg. Ontdubbeld per gebruiker,
 * kavel en soort: in een biedoorlog is er één overboden-alert die bijwerkt.
 */
#[Fillable(['user_id', 'key', 'role', 'lot_id', 'order_id', 'dedupe_key', 'level', 'payload', 'choices', 'read_at', 'acted_at', 'acted_choice', 'expires_at'])]
class IvyAlert extends Model
{
    use HasUlids;

    public const string TASK = 'task';

    public const string INFO = 'info';

    public const string DONE = 'done';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array', 'choices' => 'array', 'read_at' => 'datetime', 'acted_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(IvyAlertDelivery::class, 'alert_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('acted_at')->where(fn ($inner) => $inner->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function title(): string
    {
        return (string) ($this->payload['title'] ?? __('ivy.alerts.'.$this->key.'.title'));
    }

    public function body(): string
    {
        return (string) ($this->payload['body'] ?? '');
    }

    public function advice(): ?string
    {
        return isset($this->payload['advice']) ? (string) $this->payload['advice'] : null;
    }

    public function url(): ?string
    {
        return isset($this->payload['url']) ? (string) $this->payload['url'] : null;
    }
}
