<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VatRegime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Een factuur of creditnota bij een order. De partijen en regels staan
 * als momentopname op de factuur; een factuurnummer is opeenvolgend per
 * reeks en verandert nooit.
 */
#[Fillable([
    'number', 'series', 'sequence', 'kind', 'order_id', 'issuer_type', 'issuer_company_id', 'recipient_user_id', 'recipient_company_id',
    'credits_invoice_id', 'issued_at', 'delivery_date', 'vat_regime', 'parties', 'lines', 'subtotal_cents', 'vat_cents', 'total_cents', 'note',
])]
class Invoice extends Model
{
    /** De kavel: verkoper aan koper, opgemaakt door Aucvy namens de verkoper. */
    public const string LOT = 'lot';

    /** Administratiekosten: Aucvy aan de koper. */
    public const string ADMIN_FEE = 'admin_fee';

    /** Succesfee: Aucvy aan de verkoper, verrekend met de uitbetaling. */
    public const string FEE = 'fee';

    /** Creditnota op de kavelfactuur na een prijscorrectie. */
    public const string CREDIT = 'credit';

    public const string ISSUER_AUCVY = 'aucvy';

    public const string ISSUER_SELLER = 'seller';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'delivery_date' => 'date',
            'vat_regime' => VatRegime::class,
            'parties' => 'array',
            'lines' => 'array',
            'subtotal_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function credits(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credits_invoice_id');
    }

    public function kindLabel(): string
    {
        return __('invoices.kinds.'.$this->kind);
    }

    public function isCredit(): bool
    {
        return $this->kind === self::CREDIT;
    }

    /**
     * Wie de factuur mag zien: beide partijen van de order en beheer.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->is_staff) {
            return true;
        }

        $order = $this->order;

        return $order !== null && in_array($user->id, [$order->buyer_id, $order->seller_id], true);
    }
}
