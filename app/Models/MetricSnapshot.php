<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Een cijfer per dag, elke nacht gevuld door aucvy:build-metrics. Het
 * dashboard leest deze plus de cijfers van vandaag live.
 */
#[Fillable(['metric', 'date', 'dimension', 'value', 'created_at'])]
class MetricSnapshot extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'value' => 'integer',
        ];
    }

    public static function put(string $metric, DateTimeInterface $date, int $value, string $dimension = ''): void
    {
        self::query()->updateOrCreate(
            ['metric' => $metric, 'date' => $date->format('Y-m-d'), 'dimension' => $dimension],
            ['value' => $value, 'created_at' => now()],
        );
    }
}
