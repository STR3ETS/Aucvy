<?php

declare(strict_types=1);

namespace App\Filament\Resources\Viewings;

use App\Filament\Resources\Viewings\Pages\CreateViewing;
use App\Filament\Resources\Viewings\Pages\EditViewing;
use App\Filament\Resources\Viewings\Pages\ListViewings;
use App\Filament\Resources\Viewings\Schemas\ViewingForm;
use App\Filament\Resources\Viewings\Tables\ViewingsTable;
use App\Models\Viewing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ViewingResource extends Resource
{
    protected static ?string $model = Viewing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ViewingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ViewingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListViewings::route('/'),
            'create' => CreateViewing::route('/create'),
            'edit' => EditViewing::route('/{record}/edit'),
        ];
    }
}
