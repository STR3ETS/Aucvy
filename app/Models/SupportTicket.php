<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Een ticket bij de klantenservice: via het contactformulier, een
 * inkomende mail of de chat. Met type (vraag of klacht), prioriteit,
 * toewijzing en reactietijd (briefing nieuwe functies, Klantenservice).
 * Gekoppeld aan klant, kavel en bestelling waar dat kan; een ticket over
 * een kavel komt ook in het dossier.
 */
#[Fillable([
    'number', 'type', 'priority', 'source', 'user_id', 'name', 'email', 'subject', 'message', 'order_number',
    'lot_id', 'order_id', 'assigned_to', 'first_response_due_at', 'first_response_at', 'resolved_at',
    'mail_thread', 'sla_task_at', 'status', 'ip',
])]
class SupportTicket extends Model
{
    use HasFactory;

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const string TYPE_QUESTION = 'question';

    public const string TYPE_COMPLAINT = 'complaint';

    /** @var list<string> */
    public const PRIORITIES = ['low', 'normal', 'high'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'first_response_due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'sla_task_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket): void {
            $ticket->number ??= self::nextNumber();
            $ticket->first_response_due_at ??= now()->addHours((int) config('aucvy.support.first_response_hours', 8));
        });
    }

    public static function nextNumber(): string
    {
        $count = (int) self::query()->max('id') + 1;

        return 'T-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by')->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id')->orderBy('id');
    }

    /**
     * @param  Builder<SupportTicket>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::OPEN);
    }

    /**
     * @param  Builder<SupportTicket>  $query
     */
    public function scopeLate(Builder $query): void
    {
        $query->where('status', self::OPEN)->whereNull('first_response_at')->where('first_response_due_at', '<', now());
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    public function isLate(): bool
    {
        return $this->isOpen() && $this->first_response_at === null && $this->first_response_due_at !== null && $this->first_response_due_at->isPast();
    }

    public function isComplaint(): bool
    {
        return $this->type === self::TYPE_COMPLAINT;
    }

    public function typeLabel(): string
    {
        return __('admin.tickets.types.'.$this->type);
    }

    public function priorityLabel(): string
    {
        return __('admin.tickets.priorities.'.$this->priority);
    }
}
