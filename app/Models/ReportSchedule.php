<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Een vast rapport dat per mail gaat: wekelijks of maandelijks, aan de
 * genoemde adressen, met een link die na een dag verloopt.
 */
#[Fillable(['report_key', 'filters', 'frequency', 'recipients', 'last_sent_at', 'created_by'])]
class ReportSchedule extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'recipients' => 'array',
            'last_sent_at' => 'datetime',
        ];
    }

    public function isDue(): bool
    {
        if ($this->last_sent_at === null) {
            return true;
        }

        return $this->frequency === 'monthly'
            ? $this->last_sent_at->lessThan(now()->startOfMonth())
            : $this->last_sent_at->lessThan(now()->startOfWeek());
    }
}
