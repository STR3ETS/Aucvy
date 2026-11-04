<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['automation_id', 'user_id', 'lot_id', 'order_id', 'current_step', 'next_run_at', 'status', 'stop_reason', 'dedupe_key'])]
class AutomationRun extends Model
{
    public const string RUNNING = 'running';

    public const string DONE = 'done';

    public const string STOPPED = 'stopped';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['next_run_at' => 'datetime', 'current_step' => 'integer'];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(MailAutomation::class, 'automation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->where('status', self::RUNNING)->where('next_run_at', '<=', now());
    }

    public function stop(string $reason): void
    {
        $this->forceFill(['status' => self::STOPPED, 'stop_reason' => $reason, 'next_run_at' => null])->save();
    }
}
