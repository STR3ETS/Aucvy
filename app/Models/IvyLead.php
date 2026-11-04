<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['conversation_id', 'name', 'email', 'phone', 'intent', 'temperature', 'summary', 'consent_at', 'consent_version', 'assigned_to', 'status'])]
class IvyLead extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['consent_at' => 'datetime'];
    }
}
