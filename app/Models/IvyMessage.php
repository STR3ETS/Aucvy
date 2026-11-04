<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'sender', 'body', 'cards', 'tool_calls', 'ai_run_id', 'prompt_version', 'created_at'])]
class IvyMessage extends Model
{
    use HasUlids;

    public const null UPDATED_AT = null;

    public const string USER = 'user';

    public const string IVY = 'ivy';

    public const string SYSTEM = 'system';

    public const string STAFF = 'staff';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['cards' => 'array', 'tool_calls' => 'array', 'created_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(IvyConversation::class, 'conversation_id');
    }
}
