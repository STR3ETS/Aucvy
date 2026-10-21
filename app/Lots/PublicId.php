<?php

declare(strict_types=1);

namespace App\Lots;

use App\Models\Lot;

/**
 * Het kavelnummer dat de gebruiker ziet: nooit het autonummer (hfst. 5).
 */
final class PublicId
{
    public static function fresh(): string
    {
        do {
            $publicId = (string) random_int(1000, 99999);
        } while (Lot::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }
}
