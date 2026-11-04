<?php

declare(strict_types=1);

namespace App\Models;

use App\Distribution\Channel;
use App\Distribution\ChannelTexts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wat er per kanaal en moment met een kavel gebeurt: een concept in de
 * deelkit, een gepland bericht van de social push, of wat er geplaatst is.
 * De tekst bewaart de plekken voor het bod; die worden pas vlak voor het
 * plaatsen ingevuld.
 */
#[Fillable([
    'lot_id', 'channel', 'moment', 'client_key', 'status', 'body', 'body_source', 'visual_path', 'url', 'external_id',
    'scheduled_at', 'approved_by', 'approved_at', 'posted_at', 'withdrawn_at', 'last_error', 'clicks',
])]
class LotDistribution extends Model
{
    public const string DRAFT = 'draft';

    public const string SCHEDULED = 'scheduled';

    public const string POSTING = 'posting';

    public const string READY = 'ready';

    public const string POSTED = 'posted';

    public const string FAILED = 'failed';

    public const string OFFLINE = 'offline';

    /** De deelkit: geen moment, de verkoper plaatst zelf. */
    public const string KIT = 'kit';

    public const string START = 'start';

    public const string DAY_BEFORE = 'day_before';

    public const string LAST_HOUR = 'last_hour';

    public const MOMENTS = [self::START, self::DAY_BEFORE, self::LAST_HOUR];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'clicks' => 'integer',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function channel(): Channel
    {
        return Channel::from($this->channel);
    }

    public function channelLabel(): string
    {
        return $this->channel()->label();
    }

    public function statusLabel(): string
    {
        return __('distribution.status.'.$this->status);
    }

    public function momentLabel(): string
    {
        return __('distribution.moments.'.$this->moment);
    }

    public function isPlanned(): bool
    {
        return $this->moment !== self::KIT;
    }

    public function isLive(): bool
    {
        return in_array($this->status, [self::READY, self::POSTED], true);
    }

    /**
     * De tekst zoals hij nu geplaatst zou worden: bod, sluiting en link
     * ingevuld met de stand van dit moment.
     */
    public function renderedBody(): string
    {
        return ChannelTexts::render($this);
    }

    public static function clientKey(Lot|int $lot, Channel $channel, string $moment): string
    {
        $lotId = $lot instanceof Lot ? $lot->id : $lot;

        return "lot:{$lotId}:{$moment}:{$channel->value}";
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->where('status', self::SCHEDULED)->whereNotNull('approved_at')->where('scheduled_at', '<=', now());
    }
}
