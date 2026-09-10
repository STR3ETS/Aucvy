<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasLabel;

enum MediaType: string
{
    use HasLabel;

    case Image = 'image';
    case Video = 'video';
    case Tour360 = 'tour360';
}
