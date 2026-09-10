<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['parent_id', 'name', 'slug', 'position', 'specifications_schema'])]
#[RouteKey('slug')]
class Category extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'specifications_schema' => 'array',
            'position' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(Lot::class);
    }

    /**
     * De acht hoofdcategorieën.
     *
     * @param  Builder<Category>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id')->orderBy('position');
    }

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Het attributenschema van een subcategorie valt terug op dat van de
     * hoofdcategorie zolang het nog niet is uitgewerkt. Beginnen met drie
     * categorieën volledig en de rest generiek is een expliciete keuze - een
     * half schema suggereert volledigheid die er niet is (hfst. 5).
     *
     * @return array<string, mixed>
     */
    public function effectiveSpecificationsSchema(): array
    {
        return $this->specifications_schema
            ?? $this->parent?->specifications_schema
            ?? [];
    }
}
