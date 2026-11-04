<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Adressen waar geen marketing meer heen mag: harde bounce, spamklacht
 * of een handmatige blokkade. Opgeslagen als hash, niet als adres.
 */
#[Fillable(['email_hash', 'reason', 'source', 'created_at'])]
class MailSuppression extends Model
{
    public const null UPDATED_AT = null;

    public static function hash(string $email): string
    {
        return hash('sha256', Str::lower(trim($email)));
    }

    public static function add(string $email, string $reason, ?string $source = null): void
    {
        self::query()->firstOrCreate(['email_hash' => self::hash($email)], ['reason' => $reason, 'source' => $source, 'created_at' => now()]);
    }

    public static function has(string $email): bool
    {
        return self::query()->where('email_hash', self::hash($email))->exists();
    }
}
