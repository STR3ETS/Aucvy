<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'reported_by', 'reason', 'description', 'evidence',
    'status', 'sla_due_at', 'correction_cents',
])]
class Dispute extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'evidence' => 'array',
            'seller_responded_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by')->withTrashed();
    }

    /**
     * Een medewerker, geen algoritme. Dat staat zo op de zekerheidspagina en
     * moet waar blijven (hfst. 9).
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }
}
