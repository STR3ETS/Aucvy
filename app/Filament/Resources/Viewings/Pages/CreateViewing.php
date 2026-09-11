<?php

declare(strict_types=1);

namespace App\Filament\Resources\Viewings\Pages;

use App\Filament\Resources\Viewings\ViewingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateViewing extends CreateRecord
{
    protected static string $resource = ViewingResource::class;
}
