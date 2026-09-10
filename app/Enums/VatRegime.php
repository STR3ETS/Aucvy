<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Bepaalt de kostenregel op de kavelpagina. Bij de margeregeling is er geen
 * verrekenbare btw voor de koper - dat moet er letterlijk bij staan, want het
 * scheelt een zakelijke koper 21%.
 */
enum VatRegime: string
{
    use HasLabel;

    case Margin = 'margin';
    case Vat21 = 'vat_21';

    public function isDeductible(): bool
    {
        return $this === self::Vat21;
    }
}
