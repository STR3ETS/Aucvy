<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'company_id', 'label', 'street', 'house_number',
    'postal_code', 'city', 'province', 'country', 'latitude', 'longitude',
])]
class Location extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /**
     * Wat op de kavelpagina mag staan: plaats en provincie, meer niet. Het
     * volledige adres gaat per mail ná een bevestigde bezichtiging (hfst. 7).
     */
    public function publicLabel(): string
    {
        return $this->province !== null
            ? $this->city.', '.$this->province
            : $this->city;
    }
}
