<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Enums\VatRegime;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('order_number')
                    ->required(),
                Select::make('lot_id')
                    ->relationship('lot', 'title')
                    ->required(),
                Select::make('auction_id')
                    ->relationship('auction', 'id'),
                Select::make('buyer_id')
                    ->relationship('buyer', 'name')
                    ->required(),
                Select::make('seller_id')
                    ->relationship('seller', 'name')
                    ->required(),
                TextInput::make('hammer_cents')
                    ->required()
                    ->numeric(),
                TextInput::make('buyer_premium_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('transport_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('vat_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('total_cents')
                    ->required()
                    ->numeric(),
                Select::make('vat_regime')
                    ->options(VatRegime::class)
                    ->required(),
                Select::make('status')
                    ->options(OrderStatus::class)
                    ->default('awaiting_payment')
                    ->required(),
                DateTimePicker::make('payment_deadline_at'),
                DateTimePicker::make('paid_at'),
                DateTimePicker::make('shipped_at'),
                DateTimePicker::make('delivered_at'),
                DateTimePicker::make('dispute_window_ends_at'),
                DateTimePicker::make('completed_at'),
            ]);
    }
}
