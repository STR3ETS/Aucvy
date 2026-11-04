<?php

declare(strict_types=1);

namespace App\Models;

use App\Distribution\Channel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Een deling van een kavel door een koper: eigen token in de link, een
 * optionele notitie voor de ontvanger, en of de link is geopend. De
 * ontvanger ziet nooit het bod of de biednaam van de deler.
 */
#[Fillable(['lot_id', 'user_id', 'token', 'channel', 'note', 'open_count', 'opened_at', 'reaction', 'reaction_text', 'reacted_at', 'created_at'])]
class LotShare extends Model
{
    public const null UPDATED_AT = null;

    public const string INTERESTED = 'interested';

    public const string NOT_INTERESTED = 'not_interested';

    public const string TEXT = 'text';

    public const REACTIONS = [self::INTERESTED, self::NOT_INTERESTED, self::TEXT];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'reacted_at' => 'datetime',
            'created_at' => 'datetime',
            'open_count' => 'integer',
        ];
    }

    public static function newToken(): string
    {
        do {
            $token = Str::lower(Str::random(14));
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): Channel
    {
        return Channel::tryFrom($this->channel) ?? Channel::Link;
    }

    /**
     * De link voor de ontvanger: eigen token plus UTM, zodat de bron in het
     * dossier staat.
     */
    public function url(): string
    {
        return route('lots.shared', [$this->lot, $this->token]).'?utm_source=deel&utm_medium='.$this->channel.'&utm_campaign=kavel-'.$this->lot->public_id;
    }

    public function reactionLabel(): ?string
    {
        return $this->reaction === null ? null : __('distribution.reactions.'.$this->reaction);
    }
}
