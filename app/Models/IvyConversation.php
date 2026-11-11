<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiAgent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een gesprek met Ivy: van een bezoeker (gasttoken), koper of verkoper,
 * met de pagina en eventueel de kavel waar het over gaat. Blijft bewaard
 * tussen pagina's; een gast neemt het mee bij het aanmaken van een account.
 */
#[Fillable(['user_id', 'guest_token', 'role', 'agent', 'lot_id', 'page_type', 'status', 'summary', 'pitched', 'handoff_at', 'last_message_at'])]
class IvyConversation extends Model
{
    use HasUlids;

    public const string VISITOR = 'visitor';

    public const string BUYER = 'buyer';

    public const string SELLER = 'seller';

    public const string OPEN = 'open';

    public const string HANDED_OFF = 'handed_off';

    public const string CLOSED = 'closed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['agent' => AiAgent::class, 'pitched' => 'boolean', 'handoff_at' => 'datetime', 'last_message_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(IvyMessage::class, 'conversation_id')->orderBy('created_at')->orderBy('id');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(IvyActionProposal::class, 'conversation_id');
    }
}
