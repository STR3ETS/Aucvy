<?php

declare(strict_types=1);

namespace App\Support\TwoFactor;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * De authenticator-app als tweede factor (besluit Raph, 2 november 2026).
 *
 * - Codes zijn standaard TOTP: 6 cijfers, 30 seconden, één tijdvak speling
 *   voor een klok die iets achterloopt.
 * - Een code werkt één keer: het laatst gebruikte tijdvak staat op de
 *   gebruiker en een code uit hetzelfde of een eerder vak wordt geweigerd.
 * - Herstelcodes staan alleen als HMAC op de gebruiker; wie de database
 *   leest, kan er niet mee inloggen.
 */
final class Authenticator
{
    public const RECOVERY_CODE_COUNT = 8;

    public function __construct(private readonly Google2FA $google2fa = new Google2FA) {}

    public function newSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * De QR-code voor het koppelen, als inline SVG.
     */
    public function qrSvg(User $user, string $secret): string
    {
        $url = $this->google2fa->getQRCodeUrl((string) config('app.name'), $user->email, $secret);

        $svg = (new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd)))->writeString($url);

        // Zonder XML-declaratie, zodat hij direct in de pagina kan.
        return trim((string) preg_replace('/^<\?xml.*?\?>/', '', $svg));
    }

    /**
     * Klopt de code bij dit geheim? Voor het koppelen, vóór er iets op de
     * gebruiker staat.
     */
    public function verifySecret(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $this->digits($code), 1);
    }

    /**
     * Een inlogcode controleren en het tijdvak vastleggen. Onder een lock,
     * zodat twee gelijktijdige pogingen met dezelfde code niet allebei
     * slagen.
     */
    public function verify(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $secret = $locked->two_factor_secret;

            if ($secret === null || $locked->two_factor_confirmed_at === null) {
                return false;
            }

            $step = $this->google2fa->verifyKeyNewer($secret, $this->digits($code), $locked->two_factor_last_step, 1);

            if ($step === false) {
                return false;
            }

            $locked->forceFill(['two_factor_last_step' => $step === true ? $this->google2fa->getTimestamp() : $step])->save();

            return true;
        });
    }

    /**
     * Nieuwe herstelcodes: de gebruiker ziet ze één keer, opgeslagen worden
     * alleen de hashes.
     *
     * @return list<string>
     */
    public function newRecoveryCodes(User $user): array
    {
        $codes = collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn (): string => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();

        $user->forceFill(['two_factor_recovery_codes' => array_map($this->hash(...), $codes)])->save();

        return $codes;
    }

    /**
     * Een herstelcode inwisselen: hij werkt precies één keer.
     */
    public function useRecoveryCode(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $hashes = (array) ($locked->two_factor_recovery_codes ?? []);
            $given = $this->hash($code);

            $match = collect($hashes)->search(fn (string $hash): bool => hash_equals($hash, $given));

            if ($match === false) {
                return false;
            }

            unset($hashes[$match]);
            $locked->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

            return true;
        });
    }

    public function remainingRecoveryCodes(User $user): int
    {
        if (($user->getAttributes()['two_factor_recovery_codes'] ?? null) === null) {
            return 0;
        }

        return count((array) $user->two_factor_recovery_codes);
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', Str::lower(trim($code)), (string) config('app.key'));
    }

    private function digits(string $code): string
    {
        return (string) preg_replace('/\D/', '', $code);
    }
}
