<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Eén plek om een regel in het auditlog te schrijven (briefing sectie 9 en
 * 11): wie, wat, op welk object, van en naar, met het IP-adres erbij.
 *
 * Alleen invoegen. De rijen zijn bewijs en worden nooit bijgewerkt of
 * verwijderd; het model weigert dat (zie AuditLog).
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public static function record(
        string $action,
        Model $subject,
        ?User $actor = null,
        array $before = [],
        array $after = [],
        ?string $ip = null,
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'before' => $before === [] ? null : $before,
            'after' => $after === [] ? null : $after,
            'ip' => $ip ?? request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
