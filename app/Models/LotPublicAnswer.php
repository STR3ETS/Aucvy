<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een vraag en antwoord die de verkoper bij de kavel zette: alle bieders
 * krijgen dezelfde informatie, zonder de naam van de vrager.
 */
#[Fillable(['lot_id', 'conversation_id', 'lot_part', 'question', 'answer', 'published_by'])]
class LotPublicAnswer extends Model
{
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function partLabel(): ?string
    {
        return $this->lot_part === null ? null : __('messaging.parts.'.$this->lot_part);
    }
}
