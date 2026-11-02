<?php

declare(strict_types=1);

namespace App\Support\Privacy;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Cookietoestemming (besluit Raph, 2 november 2026: cookiebanner).
 *
 * Noodzakelijke cookies (sessie, CSRF, het concept van de wizard en deze
 * keuze zelf) staan altijd aan. Statistiek en marketing alleen na een
 * keuze van de bezoeker; zonder keuze geldt "nee". Verandert de uitleg op
 * de cookiepagina wezenlijk, verhoog dan VERSION: dan vraagt de banner
 * het opnieuw.
 */
final class CookieConsent
{
    public const COOKIE = 'aucvy_consent';

    public const VERSION = 1;

    /** @var list<string> */
    public const CATEGORIES = ['statistics', 'marketing'];

    private const DAYS = 365;

    /**
     * De keuze van deze bezoeker, of null als hij nog niets koos (of een
     * oude versie van de uitleg zag).
     *
     * @return array{v: int, statistics: bool, marketing: bool, at: string}|null
     */
    public function choice(Request $request): ?array
    {
        $raw = json_decode((string) $request->cookie(self::COOKIE), true);

        if (! is_array($raw) || ($raw['v'] ?? null) !== self::VERSION) {
            return null;
        }

        return [
            'v' => self::VERSION,
            'statistics' => ($raw['statistics'] ?? false) === true,
            'marketing' => ($raw['marketing'] ?? false) === true,
            'at' => (string) ($raw['at'] ?? ''),
        ];
    }

    public function decided(Request $request): bool
    {
        return $this->choice($request) !== null;
    }

    public function allows(Request $request, string $category): bool
    {
        return ($this->choice($request)[$category] ?? false) === true;
    }

    public function cookie(bool $statistics, bool $marketing): Cookie
    {
        $value = json_encode(['v' => self::VERSION, 'statistics' => $statistics, 'marketing' => $marketing, 'at' => now()->toIso8601String()]) ?: '{}';

        return cookie(self::COOKIE, $value, self::DAYS * 24 * 60, httpOnly: true, sameSite: 'lax');
    }
}
