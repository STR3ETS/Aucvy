<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompanyPlan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'slug', 'kvk_number', 'vat_number', 'plan', 'fee_percentage',
    'payout_iban_last4', 'payout_iban_name',
])]
#[RouteKey('slug')]
class Company extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'kyb_requested_at' => 'datetime',
            'ubo_verified_at' => 'datetime',
            'ubo_declaration' => 'array',
            'ubo_declared_at' => 'datetime',
            'plan' => CompanyPlan::class,
            'fee_percentage' => 'decimal:2',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Aangevraagd maar nog niet beoordeeld: wacht in de compliance-queue.
     */
    public function awaitsVerification(): bool
    {
        return $this->verified_at === null && $this->kyb_requested_at !== null;
    }
}
