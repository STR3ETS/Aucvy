<?php

declare(strict_types=1);

namespace App\Support\Sms;

use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Een telefoonnummer bevestigen met een code per sms (besluit Raph,
 * 2 november 2026). Zes cijfers, tien minuten geldig, alleen als hash in de
 * cache. Hooguit drie codes per tien minuten en vijf pogingen per code,
 * want een sms kost geld en een code is te raden.
 */
final class PhoneVerification
{
    private const CODE_MINUTES = 10;

    public function __construct(private readonly SmsSender $sms) {}

    /**
     * Nederlands of internationaal nummer naar E.164; null als het geen
     * bruikbaar nummer is.
     */
    public static function normalise(string $phone): ?string
    {
        $digits = (string) preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '+31'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '+')) {
            $digits = '+'.$digits;
        }

        return preg_match('/^\+[1-9][0-9]{7,14}$/', $digits) === 1 ? $digits : null;
    }

    public function sendCode(User $user): void
    {
        $key = 'phone-code-send:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages([
                'phone' => __('verification.phone.too_many', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
            ]);
        }

        RateLimiter::hit($key, 600);
        RateLimiter::clear('phone-code-check:'.$user->id);

        $code = (string) random_int(100_000, 999_999);
        Cache::put($this->cacheKey($user), ['phone' => $user->phone, 'hash' => Hash::make($code)], now()->addMinutes(self::CODE_MINUTES));

        $this->sms->send((string) $user->phone, __('verification.phone.sms', ['code' => $code, 'minutes' => self::CODE_MINUTES]));
    }

    public function confirm(User $user, string $code): void
    {
        $key = 'phone-code-check:'.$user->id;
        $pending = Cache::get($this->cacheKey($user));

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => __('verification.phone.too_many_tries')]);
        }

        // Een code hoort bij het nummer waarnaar hij ging: nummer gewijzigd is code ongeldig.
        if (! is_array($pending) || $pending['phone'] !== $user->phone || ! Hash::check(trim($code), $pending['hash'])) {
            RateLimiter::hit($key, 600);

            throw ValidationException::withMessages(['code' => __('verification.phone.invalid')]);
        }

        Cache::forget($this->cacheKey($user));
        RateLimiter::clear($key);

        $user->forceFill(['phone_verified_at' => now()])->save();

        Audit::record('user.phone_verified', $user, $user, after: ['phone_last4' => substr((string) $user->phone, -4)]);
    }

    public function hasPendingCode(User $user): bool
    {
        return Cache::has($this->cacheKey($user));
    }

    private function cacheKey(User $user): string
    {
        return 'phone-code:'.$user->id;
    }
}
