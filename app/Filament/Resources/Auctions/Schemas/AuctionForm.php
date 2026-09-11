<?php

declare(strict_types=1);

namespace App\Filament\Resources\Auctions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AuctionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('lot_id')
                    ->relationship('lot', 'title')
                    ->required(),
                DateTimePicker::make('starts_at')
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->required(),
                DateTimePicker::make('original_ends_at')
                    ->required(),
                TextInput::make('start_price_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('reserve_price_cents')
                    ->numeric(),
                Toggle::make('reserve_met')
                    ->required(),
                TextInput::make('current_price_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('bid_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('bidder_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('increment_table')
                    ->required(),
                TextInput::make('anti_snipe_window_s')
                    ->required()
                    ->numeric()
                    ->default(30),
                TextInput::make('anti_snipe_extend_s')
                    ->required()
                    ->numeric()
                    ->default(60),
                TextInput::make('extension_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('buy_now_price_cents')
                    ->numeric(),
                Toggle::make('buy_now_active')
                    ->required(),
                TextInput::make('dutch_start_cents')
                    ->numeric(),
                TextInput::make('dutch_floor_cents')
                    ->numeric(),
                TextInput::make('dutch_step_cents')
                    ->numeric(),
                TextInput::make('dutch_interval_s')
                    ->numeric(),
                TextInput::make('last_sequence')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('winner_id')
                    ->relationship('winner', 'name'),
                Select::make('winning_bid_id')
                    ->relationship('winningBid', 'id'),
                DateTimePicker::make('closed_at'),
            ]);
    }
}
