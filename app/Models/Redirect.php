<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Een doorverwijzing van een pad zonder route naar een nieuw adres.
 *
 * Het pad wordt genormaliseerd opgeslagen (voorloopslash, geen slash aan het
 * eind, kleine letters) zodat één regel alle schrijfwijzen vangt. De
 * opzoeking per pad staat tien minuten in de cache; opslaan of verwijderen
 * maakt die direct ongeldig.
 */
#[Fillable(['from_path', 'to_url', 'status'])]
class Redirect extends Model
{
    public const int CACHE_SECONDS = 600;

    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect): void {
            $redirect->from_path = self::normalise($redirect->from_path);
        });

        static::saved(fn (Redirect $redirect) => Cache::forget(self::cacheKey($redirect->from_path)));
        static::deleted(fn (Redirect $redirect) => Cache::forget(self::cacheKey($redirect->from_path)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_hit_at' => 'datetime',
            'status' => 'integer',
            'hits' => 'integer',
        ];
    }

    public static function normalise(string $path): string
    {
        $path = '/'.ltrim(trim(mb_strtolower($path)), '/');

        return $path === '/' ? $path : rtrim($path, '/');
    }

    public static function cacheKey(string $path): string
    {
        return 'redirect:'.hash('xxh128', self::normalise($path));
    }

    /**
     * De doorverwijzing voor een pad, of null. Een "geen" wordt ook
     * onthouden, anders kost elke onbekende URL een query.
     *
     * @return array{to_url: string, status: int, id: int}|null
     */
    public static function forPath(string $path): ?array
    {
        $found = Cache::remember(self::cacheKey($path), self::CACHE_SECONDS, function () use ($path): array|false {
            $redirect = self::query()->where('from_path', self::normalise($path))->first();

            return $redirect === null ? false : ['id' => $redirect->id, 'to_url' => $redirect->to_url, 'status' => $redirect->status];
        });

        return $found === false ? null : $found;
    }
}
