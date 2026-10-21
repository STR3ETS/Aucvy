<?php

declare(strict_types=1);

namespace App\Compliance\Actions;

use App\Models\AutoBid;
use App\Models\User;
use App\Support\Audit\Audit;

/**
 * Een account blokkeren of weer vrijgeven. Geblokkeerd betekent: de
 * Permission Engine weigert bieden, publiceren en uitbetalen; inzien blijft
 * mogelijk. De reden staat op het account en in het auditlog.
 */
final class BlockUser
{
    public function block(User $user, User $staff, string $reason): User
    {
        $user->blocked_at = now();
        $user->blocked_reason = $reason;
        $user->save();

        // Wie geblokkeerd is biedt ook niet meer automatisch mee.
        $autoBids = AutoBid::query()->where('user_id', $user->id)->where('active', true)->update(['active' => false]);

        Audit::record('user.blocked', $user, $staff, after: ['reason' => $reason, 'auto_bids_deactivated' => $autoBids]);

        return $user;
    }

    public function unblock(User $user, User $staff, ?string $note = null): User
    {
        $reason = $user->blocked_reason;

        $user->blocked_at = null;
        $user->blocked_reason = null;
        $user->save();

        Audit::record('user.unblocked', $user, $staff, ['reason' => $reason], ['note' => $note]);

        return $user;
    }
}
