<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Toestemming per stroom (activatie, campagne). Service staat vast in
 * code en kent geen toestemming.
 */
#[Fillable(['user_id', 'stream', 'status', 'source', 'changed_at'])]
class MailConsent extends Model
{
    public $timestamps = false;

    public const string GRANTED = 'granted';

    public const string REVOKED = 'revoked';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public static function set(User $user, string $stream, string $status, string $source): self
    {
        return self::query()->updateOrCreate(
            ['user_id' => $user->id, 'stream' => $stream],
            ['status' => $status, 'source' => $source, 'changed_at' => now()],
        );
    }

    public static function statusFor(User $user, string $stream): ?string
    {
        return self::query()->where('user_id', $user->id)->where('stream', $stream)->value('status');
    }
}
