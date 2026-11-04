<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['alert_id', 'channel', 'status', 'provider_id', 'sent_at', 'delivered_at', 'clicked_at', 'failed_reason'])]
class IvyAlertDelivery extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'delivered_at' => 'datetime', 'clicked_at' => 'datetime'];
    }
}
