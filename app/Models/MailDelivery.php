<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén verzonden mail en wat Mailgun erover terugmeldde.
 */
#[Fillable(['message_id', 'to_email', 'user_id', 'subject', 'kind', 'mailer', 'status', 'failure_reason', 'last_event_at'])]
class MailDelivery extends Model
{
    public const string STATUS_SENT = 'sent';

    public const string STATUS_DELIVERED = 'delivered';

    public const string STATUS_DEFERRED = 'deferred';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_COMPLAINED = 'complained';

    /** @var list<string> Wat niet meer goed komt: verdient aandacht. */
    public const PROBLEMS = [self::STATUS_FAILED, self::STATUS_COMPLAINED];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_event_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
