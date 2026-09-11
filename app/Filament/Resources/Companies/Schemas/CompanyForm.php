<?php

declare(strict_types=1);

namespace App\Filament\Resources\Companies\Schemas;

use App\Enums\CompanyPlan;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('kvk_number'),
                TextInput::make('vat_number'),
                DateTimePicker::make('verified_at'),
                DateTimePicker::make('ubo_verified_at'),
                Select::make('plan')
                    ->options(CompanyPlan::class)
                    ->default('start')
                    ->required(),
                TextInput::make('fee_percentage')
                    ->required()
                    ->numeric()
                    ->default(7.5),
                TextInput::make('payout_iban_last4'),
                TextInput::make('payout_iban_name'),
            ]);
    }
}
