<?php

declare(strict_types=1);

namespace App\Support\TwoFactor;

use App\Models\User;
use App\Notifications\TwoFactorCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * De inlogcode per mail (hfst. 15): zes cijfers, tien minuten geldig, en
 * alleen als hash in de cache. Voor wie geen app heeft, en als terugval
 * voor wie zijn telefoon niet bij de hand heeft.
 */
final class MailCode
{
    public function send(User $user): void
    {
        $code = (string) random_int(100_000, 999_999);

        Cache::put($this->key($user), Hash::make($code), now()->addMinutes(10));

        $user->notify(new TwoFactorCode($code));
    }

    public function check(User $user, string $code): bool
    {
        $hashed = Cache::get($this->key($user));

        if ($hashed === null || ! Hash::check($code, $hashed)) {
            return false;
        }

        Cache::forget($this->key($user));

        return true;
    }

    private function key(User $user): string
    {
        return 'two_factor_code:'.$user->id;
    }
}
