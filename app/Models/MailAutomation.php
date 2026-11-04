<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een reeks met een doel: start, wacht, controle, mail, taak en einde.
 * Stopt zodra het doel is bereikt.
 */
#[Fillable(['key', 'number', 'name', 'stream', 'status', 'steps', 'goal_event', 'weekly_limit_applies', 'description'])]
class MailAutomation extends Model
{
    public const string ACTIVE = 'active';

    public const string PAUSED = 'paused';

    /** Gebouwd met een bestaande melding of opdracht, buiten de reeksmotor. */
    public const string BUILT_IN = 'built_in';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['steps' => 'array', 'weekly_limit_applies' => 'boolean'];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class, 'automation_id');
    }

    public function statusLabel(): string
    {
        return __('mailbuilder.automation_status.'.$this->status);
    }
}
