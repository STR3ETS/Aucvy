<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompanyRole;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable([
    'company_id', 'company_role', 'name', 'display_name', 'email', 'password', 'type',
    'phone', 'kyc_status', 'notification_prefs',
])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
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
            'kyc_requested_at' => 'datetime',
            'blocked_at' => 'datetime',
            'password' => 'hashed',
            'type' => UserType::class,
            'company_role' => CompanyRole::class,
            'kyc_status' => KycStatus::class,
            'can_bid' => 'boolean',
            'is_staff' => 'boolean',
            'notification_prefs' => 'array',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_step' => 'integer',
        ];
    }

    /**
     * Geblokkeerd door beheer: niets wat geld of publicatie raakt mag nog.
     * De Permission Engine leest dit als eerste.
     */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
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
     * (hfst. 15): wie geld kan ontvangen, logt in twee stappen in. Beheer
     * altijd (briefing sectie 2).
     */
    public function requiresSecondFactor(): bool
    {
        return $this->hasAuthenticatorApp() || $this->is_staff || $this->company?->payout_iban_last4 !== null;
    }

    /**
     * Een gekoppelde authenticator-app (besluit Raph, 2 november 2026). Wie
     * die heeft, logt er altijd mee in; de mailcode is dan de terugval.
     */
    public function hasAuthenticatorApp(): bool
    {
        // Ruwe attributen: een net aangemaakte gebruiker heeft deze kolommen nog niet geladen.
        $attributes = $this->getAttributes();

        return ($attributes['two_factor_confirmed_at'] ?? null) !== null && ($attributes['two_factor_secret'] ?? null) !== null;
    }

    /**
     * Meldingsvoorkeur (briefing sectie 2). Alles staat aan tot de gebruiker
     * het uitzet; transactionele berichten lopen hier niet langs.
     */
    public function prefers(string $preference): bool
    {
        return (bool) ($this->notification_prefs[$preference] ?? true);
    }

    // Organisatierollen (briefing sectie 2) ---------------------------------

    /**
     * De rol binnen het bedrijf. Wie bij een bedrijf hoort zonder rol (data
     * van vóór de rollen) telt als eigenaar: zo was het ook.
     */
    public function companyRole(): ?CompanyRole
    {
        if ($this->company_id === null) {
            return null;
        }

        return $this->company_role ?? CompanyRole::Owner;
    }

    public function canManageTeam(): bool
    {
        return $this->companyRole()?->canManageTeam() ?? false;
    }

    public function canEditCompanyLots(): bool
    {
        return $this->companyRole()?->canEditLots() ?? false;
    }

    /**
     * Particulieren zien altijd hun eigen cijfers; in een bedrijf bepaalt
     * de rol het.
     */
    public function canSeeCompanyFinance(): bool
    {
        return $this->company_id === null || ($this->companyRole()?->canSeeFinance() ?? false);
    }

    /**
     * Wiens kavels deze gebruiker in "Mijn kavels" ziet en mag bewerken.
     *
     * @return list<int>
     */
    public function lotSellerIds(): array
    {
        return $this->canEditCompanyLots() ? $this->colleagueIds() : [$this->id];
    }

    /**
     * Wiens verkopen in financiën en uitbetalingen meetellen.
     *
     * @return list<int>
     */
    public function financeSellerIds(): array
    {
        return $this->company_id !== null && $this->canSeeCompanyFinance() ? $this->colleagueIds() : [$this->id];
    }

    /**
     * @return list<int>
     */
    /** @var list<int>|null */
    private ?array $colleagueIdsForRequest = null;

    private function colleagueIds(): array
    {
        // Eén keer per verzoek: het dashboard vraagt dit lijstje acht keer.
        return $this->colleagueIdsForRequest ??= User::query()->where('company_id', $this->company_id)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all() ?: [$this->id];
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
    /**
     * Heeft een publiek verkopersprofiel: ooit een kavel in de verkoop gehad
     * of een review als verkoper. Kopers en medewerkers hebben geen profiel.
     */
    public function hasSellerProfile(): bool
    {
        return Lot::query()->where('seller_id', $this->id)->public()->exists()
            || Review::query()->where('seller_id', $this->id)->exists();
    }

    /**
     * Alle andere sessies van deze gebruiker beëindigen en de
     * herinneringstoken wisselen, zodat ook een "ingelogd blijven"-cookie op
     * een ander apparaat niets meer waard is. De huidige sessie blijft.
     */
    public function revokeOtherSessions(?string $keepSessionId = null): void
    {
        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $this->id)
                ->when($keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
                ->delete();
        }

        $this->setRememberToken(Str::random(60));
        $this->save();
    }

    /**
     * De biednaam (N7, besluit Raph 2 november 2026): Aucvy maakt er een,
     * zoals "Bieder 4821", en de koper kan hem wijzigen. Nooit de echte naam.
     */
    public function publicName(): string
    {
        if ($this->display_name !== null && $this->display_name !== '') {
            return $this->display_name;
        }

        return 'Bieder '.$this->bidderNumber();
    }

    public function bidderNumber(): int
    {
        $number = $this->getAttributes()['bidder_number'] ?? null;

        // Een ander exemplaar van dezelfde gebruiker kan het nummer al gemaakt hebben.
        if ($number === null && $this->exists) {
            $number = self::query()->whereKey($this->id)->value('bidder_number');
        }

        if ($number !== null) {
            $this->setAttribute('bidder_number', (int) $number);

            return (int) $number;
        }

        // Een willekeurig nummer dat nog vrij is; nooit het id, dat verraadt het aantal accounts.
        do {
            $number = random_int(1000, 99999);
        } while (self::query()->where('bidder_number', $number)->exists());

        if ($this->exists) {
            self::query()->whereKey($this->id)->update(['bidder_number' => $number]);
        }

        $this->setAttribute('bidder_number', $number);

        return $number;
    }
}
