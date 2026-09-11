<?php

declare(strict_types=1);

namespace App\Filament\Resources\Viewings\Pages;

use App\Filament\Resources\Viewings\ViewingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditViewing extends EditRecord
{
    protected static string $resource = ViewingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
