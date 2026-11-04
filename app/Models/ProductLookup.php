<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * De eigen cache van EAN-opzoekingen: eerst hier kijken, dan pas bij een
 * EAN-dienst. Productdata toont altijd de bron.
 */
#[Fillable(['gtin', 'provider', 'status', 'payload', 'fetched_at'])]
class ProductLookup extends Model
{
    public $timestamps = false;

    public const string FOUND = 'found';

    public const string NOT_FOUND = 'not_found';

    public const string NO_PROVIDER = 'no_provider';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array', 'fetched_at' => 'datetime'];
    }
}
