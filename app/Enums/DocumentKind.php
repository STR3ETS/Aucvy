<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

/**
 * Documenten staan altijd achter een signed URL, nooit publiek (hfst. 15).
 */
enum DocumentKind: string
{
    use HasLabel;

    case Invoice = 'invoice';
    case Warranty = 'warranty';
    case Ownership = 'ownership';
    case Manual = 'manual';
    case Other = 'other';
}
