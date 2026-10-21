<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén CSV-import: bestand, kolommen, mapping en het rapport.
 */
#[Fillable(['user_id', 'filename', 'path', 'headers', 'mapping', 'status', 'rows_total', 'rows_created', 'errors'])]
class LotImport extends Model
{
    public const UPLOADED = 'uploaded';

    public const DONE = 'done';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'mapping' => 'array',
            'errors' => 'array',
            'rows_total' => 'integer',
            'rows_created' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function isDone(): bool
    {
        return $this->status === self::DONE;
    }
}
