<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bids\Schemas;

use App\Enums\BidStatus;
use App\Enums\BidType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BidForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('auction_id')
                    ->relationship('auction', 'id')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('amount_cents')
                    ->required()
                    ->numeric(),
                Select::make('type')
                    ->options(BidType::class)
                    ->default('manual')
                    ->required(),
                Select::make('status')
                    ->options(BidStatus::class)
                    ->default('winning')
                    ->required(),
                TextInput::make('sequence')
                    ->required()
                    ->numeric(),
                TextInput::make('idempotency_key')
                    ->required(),
                DateTimePicker::make('placed_at')
                    ->required(),
                TextInput::make('ip'),
                Textarea::make('user_agent')
                    ->columnSpanFull(),
            ]);
    }
}
