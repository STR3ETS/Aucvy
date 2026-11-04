<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Elke inzage in een gesprek door een medewerker, met de reden als er
 * geen vlag was (briefing: deelnemers zien dat een medewerker meekijkt).
 */
#[Fillable(['conversation_id', 'staff_id', 'reason', 'opened_at'])]
class ConversationAccessLog extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['opened_at' => 'datetime'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id')->withTrashed();
    }
}
