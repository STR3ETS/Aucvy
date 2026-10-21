<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén bericht in de draad van een bestelling: van de koper, de verkoper of
 * support. Wat gezegd is blijft staan; bewerken of verwijderen bestaat niet.
 */
#[Fillable(['order_id', 'user_id', 'from_staff', 'body'])]
class OrderMessage extends Model
{
    public const MAX_LENGTH = 3000;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_staff' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Hoe de afzender heet voor de lezer: support, koper of verkoper.
     */
    public function roleFor(Order $order): string
    {
        if ($this->from_staff) {
            return 'support';
        }

        return $this->user_id === $order->buyer_id ? 'buyer' : 'seller';
    }
}
