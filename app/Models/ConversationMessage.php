<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eén bericht in een gesprek. De staat zegt wat de ontvanger ziet:
 * zichtbaar, gemaskeerd (contactgegevens afgeschermd), vastgehouden
 * (alleen afzender en beheer) of geweigerd (alleen afzender en beheer,
 * met uitleg).
 */
#[Fillable([
    'conversation_id', 'sender_type', 'sender_id', 'body', 'body_masked', 'state', 'hint',
    'internal', 'attachment_path', 'attachment_name', 'attachment_mime', 'client_key', 'created_at',
])]
class ConversationMessage extends Model
{
    public const null UPDATED_AT = null;

    public const int MAX_LENGTH = 3000;

    public const string FROM_BUYER = 'buyer';

    public const string FROM_SELLER = 'seller';

    public const string FROM_IVY = 'ivy';

    public const string FROM_OPERATOR = 'operator';

    public const string FROM_SYSTEM = 'system';

    public const string VISIBLE = 'visible';

    public const string MASKED = 'masked';

    public const string HELD = 'held';

    public const string REJECTED = 'rejected';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'internal' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    public function moderations(): HasMany
    {
        return $this->hasMany(MessageModeration::class, 'message_id');
    }

    /**
     * Wat deze lezer te zien krijgt. De afzender en beheer zien altijd de
     * originele tekst; de ander ziet de gemaskeerde versie, of niets zolang
     * het bericht vastgehouden of geweigerd is.
     */
    public function bodyFor(?User $viewer, bool $asStaff = false): ?string
    {
        if ($asStaff || ($viewer !== null && $viewer->id === $this->sender_id)) {
            return $this->body;
        }

        return match ($this->state) {
            self::VISIBLE => $this->body,
            self::MASKED => $this->body_masked ?? $this->body,
            default => null,
        };
    }

    public function isPending(): bool
    {
        return in_array($this->state, [self::HELD, self::REJECTED], true);
    }

    public function isFrom(User $user): bool
    {
        return $this->sender_id === $user->id;
    }
}
