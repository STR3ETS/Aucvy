<?php

declare(strict_types=1);

namespace App\Filament\Resources\Disputes\Schemas;

use App\Enums\DisputeStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DisputeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'id')
                    ->required(),
                TextInput::make('reported_by')
                    ->required()
                    ->numeric(),
                TextInput::make('reason')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('evidence'),
                Select::make('status')
                    ->options(DisputeStatus::class)
                    ->default('reported')
                    ->required(),
                DateTimePicker::make('seller_responded_at'),
                Textarea::make('seller_response')
                    ->columnSpanFull(),
                DateTimePicker::make('sla_due_at'),
                TextInput::make('resolution'),
                Textarea::make('resolution_notes')
                    ->columnSpanFull(),
                TextInput::make('handled_by')
                    ->numeric(),
                DateTimePicker::make('resolved_at'),
            ]);
    }
}
