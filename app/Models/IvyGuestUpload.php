<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Een foto van een bezoeker zonder account: privé, verdwijnt na de
 * termijn als er geen account volgt, nooit om modellen te trainen.
 */
#[Fillable(['guest_token', 'path', 'mime', 'checksum', 'analysis', 'expires_at', 'claimed_listing_id', 'created_at'])]
class IvyGuestUpload extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['analysis' => 'array', 'expires_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
