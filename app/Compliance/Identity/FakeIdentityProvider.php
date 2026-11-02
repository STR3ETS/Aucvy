<?php

declare(strict_types=1);

namespace App\Compliance\Identity;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Een nepbank voor lokaal en tests: dezelfde heen-en-terugreis als iDIN,
 * met een pagina op Aucvy zelf. De terugkeer is ondertekend, zodat ook
 * hier een zelfgemaakte "gelukt"-URL niets doet.
 */
final class FakeIdentityProvider implements IdentityProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function start(User $user, string $returnUrl): string
    {
        return URL::temporarySignedRoute('verification.identity.fake', now()->addMinutes(15), [
            'user' => $user->id,
            'return' => $returnUrl,
        ]);
    }

    public function complete(User $user, Request $request): IdentityResult
    {
        if (! $request->hasValidSignature() || (int) $request->query('user') !== $user->id) {
            return IdentityResult::failed('invalid_return');
        }

        return match ($request->query('status')) {
            'ok' => new IdentityResult(verified: true, adult: $request->query('adult') !== '0', reference: 'fake-'.Str::lower(Str::random(10))),
            default => IdentityResult::failed('cancelled'),
        };
    }

    /**
     * De terugkeer-URL die de nepbank meegeeft.
     */
    public static function returnUrl(User $user, string $status, bool $adult = true): string
    {
        return URL::temporarySignedRoute('verification.identity.return', now()->addMinutes(15), [
            'user' => $user->id,
            'status' => $status,
            'adult' => $adult ? '1' : '0',
        ]);
    }
}
