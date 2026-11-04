<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén gemeten signaal van interesse: een kijker, een weergave, opgeslagen,
 * gedeeld of een geopend document. Per bezoeker en per dag één keer (op
 * weergaven na, die tellen elke keer).
 */
#[Fillable(['lot_id', 'kind', 'visitor_hash', 'user_id', 'source', 'bucket', 'occurred_at'])]
class LotSignal extends Model
{
    public $timestamps = false;

    public const string VIEWER = 'viewer';

    public const string VIEW = 'view';

    public const string SAVED = 'saved';

    public const string SHARED = 'shared';

    public const string DOCUMENT = 'document';

    public const KINDS = [self::VIEWER, self::VIEW, self::SAVED, self::SHARED, self::DOCUMENT];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
