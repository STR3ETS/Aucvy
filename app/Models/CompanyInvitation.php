<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompanyRole;
use Database\Factories\CompanyInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een uitnodiging om bij een bedrijfsaccount te komen, met de rol die de
 * uitnodiger koos. De token staat in de maillink en is de enige sleutel.
 */
#[Fillable(['company_id', 'invited_by', 'email', 'role', 'token', 'expires_at', 'accepted_at'])]
#[RouteKey('token')]
class CompanyInvitation extends Model
{
    /** @use HasFactory<CompanyInvitationFactory> */
    use HasFactory;

    public const VALID_DAYS = 14;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => CompanyRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by')->withTrashed();
    }

    /**
     * @param  Builder<CompanyInvitation>  $query
     * @return Builder<CompanyInvitation>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
