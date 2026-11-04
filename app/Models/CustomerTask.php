<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een taak voor het team, op de klantkaart of in "Wacht op jou" van het
 * control center. Een specialist (Finn, Flow, Lex) kan er een aanmaken
 * vanuit een controle; een mens vinkt hem af.
 */
#[Fillable(['user_id', 'company_id', 'title', 'agent', 'url', 'due_at', 'assigned_to', 'done_at', 'done_by', 'source_ref', 'created_by'])]
class CustomerTask extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /**
     * @param  Builder<CustomerTask>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('done_at');
    }

    /**
     * Eén taak per bron; een controle die elke dag draait maakt niet elke
     * dag dezelfde taak.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function ensure(string $sourceRef, array $attributes): self
    {
        return self::query()->firstOrCreate(['source_ref' => $sourceRef], $attributes);
    }
}
