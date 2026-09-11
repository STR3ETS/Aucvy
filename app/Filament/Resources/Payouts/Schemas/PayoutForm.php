<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payouts\Schemas;

use App\Enums\PayoutStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PayoutForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'id')
                    ->required(),
                Select::make('seller_id')
                    ->relationship('seller', 'name')
                    ->required(),
                TextInput::make('amount_cents')
                    ->required()
                    ->numeric(),
                TextInput::make('fee_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                Select::make('status')
                    ->options(PayoutStatus::class)
                    ->default('scheduled')
                    ->required(),
                DateTimePicker::make('hold_until'),
                DateTimePicker::make('released_at'),
                DateTimePicker::make('paid_at'),
                TextInput::make('provider'),
                TextInput::make('provider_payout_id'),
                TextInput::make('failure_reason'),
            ]);
    }
}
