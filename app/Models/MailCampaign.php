<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een losse campagne uit de Mail Builder: alleen met toestemming, de
 * kavels en biedingen worden bij het versturen opgehaald.
 */
#[Fillable(['template_version_id', 'name', 'segment', 'scheduled_at', 'status', 'sent_count', 'skipped_count', 'created_by', 'sent_at'])]
class MailCampaign extends Model
{
    public const string DRAFT = 'draft';

    public const string SCHEDULED = 'scheduled';

    public const string SENDING = 'sending';

    public const string SENT = 'sent';

    public const string CANCELLED = 'cancelled';

    public const AUDIENCES = ['all', 'buyers', 'sellers', 'category'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['segment' => 'array', 'scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(MailTemplateVersion::class, 'template_version_id');
    }

    public function statusLabel(): string
    {
        return __('mailbuilder.campaign_status.'.$this->status);
    }

    public function audienceLabel(): string
    {
        return __('mailbuilder.audiences.'.($this->segment['audience'] ?? 'all'));
    }
}
