<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum LotCondition: string
{
    use HasLabel;

    case New = 'new';
    case LikeNew = 'like_new';
    case Good = 'good';
    case Used = 'used';
    case Defective = 'defective';
}
