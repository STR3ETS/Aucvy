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
    case Inspection = 'inspection';
    case Maintenance = 'maintenance';
    case Logbook = 'logbook';
    case PickupInstruction = 'pickup_instruction';
    case Nameplate = 'nameplate';
    case Other = 'other';

    /**
     * Een typeplaat is een foto, de rest is een pdf.
     *
     * @return list<string>
     */
    public static function acceptedMimes(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    }
}
