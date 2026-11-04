<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use Illuminate\Support\Facades\Cache;

/**
 * De noodrem per onderdeel (Ivy-briefing hfst. 13): ivy.enabled,
 * ivy.website, ivy.seller, ivy.buyer, ivy.proposals, ivy.reserve_change,
 * plus auctions.dutch. Standaard uit de config, in beheer om te zetten
 * zonder deploy; de stand staat in de cache.
 */
final class IvyFlags
{
    public const KEYS = ['ivy.enabled', 'ivy.website', 'ivy.seller', 'ivy.buyer', 'ivy.proposals', 'ivy.reserve_change', 'auctions.dutch'];

    public static function on(string $key): bool
    {
        $override = Cache::get('ivy.flags');

        if (is_array($override) && array_key_exists($key, $override)) {
            return (bool) $override[$key];
        }

        // De sleutels bevatten een punt, dus niet via de puntnotatie van config().
        $defaults = (array) config('aucvy.ivy.flags', []);

        return (bool) ($defaults[$key] ?? false);
    }

    public static function set(string $key, bool $value): void
    {
        $flags = (array) (Cache::get('ivy.flags') ?? []);
        $flags[$key] = $value;
        Cache::forever('ivy.flags', $flags);
    }

    /**
     * @return array<string, bool>
     */
    public static function all(): array
    {
        $all = [];

        foreach (self::KEYS as $key) {
            $all[$key] = self::on($key);
        }

        return $all;
    }

    public static function forRole(string $role): bool
    {
        return self::on('ivy.enabled') && (bool) config('aucvy.ai.enabled') && self::on('ivy.'.match ($role) {
            'visitor' => 'website',
            'seller' => 'seller',
            default => 'buyer',
        });
    }
}
