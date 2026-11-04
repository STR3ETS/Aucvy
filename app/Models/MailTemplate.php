<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eén van de 46 mails, met versies. Een verstuurde mail houdt de versie
 * waarmee hij is verstuurd.
 */
#[Fillable(['key', 'number', 'stream', 'group', 'name', 'trigger', 'notification', 'current_version_id'])]
class MailTemplate extends Model
{
    public const string SERVICE = 'service';

    public const string ACTIVATION = 'activation';

    public const string CAMPAIGN = 'campaign';

    public const STREAMS = [self::SERVICE, self::ACTIVATION, self::CAMPAIGN];

    public const MARKETING = [self::ACTIVATION, self::CAMPAIGN];

    public function versions(): HasMany
    {
        return $this->hasMany(MailTemplateVersion::class, 'template_id')->orderByDesc('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(MailTemplateVersion::class, 'current_version_id');
    }

    public function isMarketing(): bool
    {
        return in_array($this->stream, self::MARKETING, true);
    }

    public function streamLabel(): string
    {
        return __('mailbuilder.streams.'.$this->stream);
    }
}
