<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KycStatus;
use App\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable([
    'company_id', 'name', 'display_name', 'email', 'password', 'type',
    'phone', 'kyc_status', 'notification_prefs',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'kyc_verified_at' => 'datetime',
            'password' => 'hashed',
            'type' => UserType::class,
            'kyc_status' => KycStatus::class,
            'can_bid' => 'boolean',
            'is_staff' => 'boolean',
            'notification_prefs' => 'array',
        ];
    }

    /**
     * De biedvlag opnieuw afleiden - de enige plek waar hij gezet wordt.
     * Bieden vraagt een bevestigd e-mailadres én een geslaagde
     * identiteitscheck (hfst. 13.6); de gate zelf zit hard in PlaceBid.
     */
    public function refreshBidPermission(): void
    {
        $this->can_bid = $this->hasVerifiedEmail() && $this->kyc_status->allowsBidding();
        $this->save();
    }

    /**
     * 2FA is verplicht zodra er een uitbetaling aan het account hangt
     * (hfst. 15): wie geld kan ontvangen, logt in twee stappen in.
     */
    public function requiresSecondFactor(): bool
    {
        return $this->company?->payout_iban_last4 !== null;
    }

    // Relaties -------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class, 'seller_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function autoBids(): HasMany
    {
        return $this->hasMany(AutoBid::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function watchlistItems(): HasMany
    {
        return $this->hasMany(WatchlistItem::class);
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class);
    }

    public function insights(): HasMany
    {
        return $this->hasMany(AiInsight::class);
    }

    // Scopes ---------------------------------------------------------------

    /**
     * @param  Builder<User>  $query
     */
    public function scopeVerified(Builder $query): void
    {
        $query->where('kyc_status', KycStatus::Verified);
    }

    // Weergave -------------------------------------------------------------

    /**
     * Wat het publiek te zien krijgt: "R. Jansen". Nooit de volledige naam,
     * nooit een e-mailadres (hfst. 15).
     *
     * Valt terug op een afkorting van de echte naam zolang de gebruiker zelf
     * geen weergavenaam heeft gekozen.
     */
    public function publicName(): string
    {
        if ($this->display_name !== null && $this->display_name !== '') {
            return $this->display_name;
        }

        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        if (count($parts) < 2) {
            return $parts[0] ?? 'Onbekend';
        }

        $initial = mb_strtoupper(mb_substr((string) array_shift($parts), 0, 1));

        return $initial.'. '.implode(' ', $parts);
    }
}
