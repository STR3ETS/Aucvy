<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ViewingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lot_id', 'user_id', 'slot_at', 'status', 'note'])]
class Viewing extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_at' => 'datetime',
            'status' => ViewingStatus::class,
            'address_sent_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Het adres gaat pas per mail ná bevestiging - nooit op de kavelpagina.
     */
    public function mayReceiveAddress(): bool
    {
        return $this->status->revealsAddress();
    }
}
