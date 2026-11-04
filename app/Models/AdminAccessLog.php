<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Elke export en elke geopende klantkaart (briefing nieuwe functies,
 * Rechten): wie keek wanneer naar wie, met reden als die er is.
 */
#[Fillable(['staff_id', 'subject_type', 'subject_id', 'reason', 'ip', 'opened_at'])]
class AdminAccessLog extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['opened_at' => 'datetime'];
    }

    public static function record(User $staff, string $subjectType, string|int $subjectId, ?string $reason = null, ?string $ip = null): self
    {
        return self::create([
            'staff_id' => $staff->id,
            'subject_type' => $subjectType,
            'subject_id' => (string) $subjectId,
            'reason' => $reason,
            'ip' => $ip,
            'opened_at' => now(),
        ]);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id')->withTrashed();
    }
}
