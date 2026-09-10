<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('lot_media')]
#[Fillable([
    'lot_id', 'type', 'path', 'variants', 'width', 'height', 'position',
    'alt', 'checksum', 'size_bytes',
])]
class LotMedia extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'variants' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /**
     * Vaste beeldverhouding voorkomt layout shift bij het laden (hfst. 12).
     * Valt terug op 4:3 zolang de afmetingen nog niet uitgelezen zijn.
     */
    public function aspectRatio(): float
    {
        if ($this->width === null || $this->height === null || $this->height === 0) {
            return 4 / 3;
        }

        return $this->width / $this->height;
    }
}
