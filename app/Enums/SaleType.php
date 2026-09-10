<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * De drie verkoopvormen. Bouwvolgorde uit hfst. 13.3: eerst `Auction` volledig
 * af inclusief alle states, pas daarna `Dutch`, pas daarna `BuyNow`.
 * Drie halve verkoopvormen is erger dan één hele.
 */
enum SaleType: string
{
    use HasLabel;

    case Auction = 'auction';
    case Dutch = 'dutch';
    case BuyNow = 'buy_now';
}
