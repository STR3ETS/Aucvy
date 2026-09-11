<?php

declare(strict_types=1);

namespace App\Filament\Resources\Viewings\Schemas;

use App\Enums\ViewingStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ViewingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('lot_id')
                    ->relationship('lot', 'title')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                DateTimePicker::make('slot_at')
                    ->required(),
                Select::make('status')
                    ->options(ViewingStatus::class)
                    ->default('requested')
                    ->required(),
                Textarea::make('note')
                    ->columnSpanFull(),
                DateTimePicker::make('address_sent_at'),
            ]);
    }
}
