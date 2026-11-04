<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eén bericht in een ticket: van de klant, van een medewerker, of een
 * concept van Ivy dat pas na een klik van een medewerker de deur uitgaat.
 */
#[Fillable(['ticket_id', 'sender_type', 'sender_id', 'body', 'internal', 'draft', 'mail_delivery_id', 'created_at'])]
class TicketMessage extends Model
{
    public const null UPDATED_AT = null;

    public const string FROM_CUSTOMER = 'customer';

    public const string FROM_STAFF = 'staff';

    public const string FROM_IVY = 'ivy';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'internal' => 'boolean',
            'draft' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }
}
