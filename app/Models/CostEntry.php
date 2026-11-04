<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Kosten die niet uit de betaalpartij komen: AI en hosting, mail en live,
 * marketing, team, overig. Handmatig of via de boekhouding, per maand.
 */
#[Fillable(['category', 'amount_cents', 'period', 'source', 'note', 'created_by'])]
class CostEntry extends Model
{
    /** @var list<string> */
    public const CATEGORIES = ['payment', 'ai_hosting', 'mail_live', 'marketing', 'team', 'other'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'period' => 'date',
        ];
    }

    public function categoryLabel(): string
    {
        return __('admin.costs.'.$this->category);
    }
}
