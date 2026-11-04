<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiAgent;
use App\Enums\AiRunStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Elke AI-uitkomst, geslaagd of niet.
 *
 * Zonder dit log kun je later niet uitleggen waarom Ivy iets zei, en dat ga je
 * een keer moeten kunnen (hfst. 8).
 */
#[Fillable([
    'subject_type', 'subject_id', 'user_id', 'agent', 'task', 'model',
    'input', 'output', 'confidence', 'tokens_in', 'tokens_out', 'cost_cents', 'conversation_id', 'ivy_role',
    'latency_ms', 'status', 'error', 'cache_key',
])]
class AiRun extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agent' => AiAgent::class,
            'status' => AiRunStatus::class,
            'input' => 'array',
            'output' => 'array',
            'confidence' => 'float',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'cost_cents' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function succeeded(): bool
    {
        return $this->status === AiRunStatus::Completed;
    }
}
