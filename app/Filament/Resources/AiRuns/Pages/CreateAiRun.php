<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiRuns\Pages;

use App\Filament\Resources\AiRuns\AiRunResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAiRun extends CreateRecord
{
    protected static string $resource = AiRunResource::class;
}
