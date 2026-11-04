<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén beslissing van de bot over een bericht: regelversie, promptversie,
 * signaal, zekerheid en actie, en wie hem eventueel terugdraaide.
 */
#[Fillable([
    'message_id', 'rule_version', 'prompt_version', 'signal', 'confidence', 'action',
    'explanation', 'reviewed_by', 'reviewed_at', 'review_action', 'created_at',
])]
class MessageModeration extends Model
{
    public const null UPDATED_AT = null;

    public const string ALLOW = 'allow';

    public const string MASK = 'mask';

    public const string HOLD = 'hold';

    public const string REJECT = 'reject';

    public const string FLAG = 'flag';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'message_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function signalLabel(): string
    {
        return __('messaging.signals.'.$this->signal);
    }
}
