<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\KycStatus;
use App\Enums\UserType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('company_id')
                    ->relationship('company', 'name'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('display_name'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                Select::make('type')
                    ->options(UserType::class)
                    ->default('private')
                    ->required(),
                Select::make('kyc_status')
                    ->options(KycStatus::class)
                    ->default('unverified')
                    ->required(),
                DateTimePicker::make('kyc_verified_at'),
                Toggle::make('can_bid')
                    ->required(),
                Toggle::make('is_staff')
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('phone')
                    ->tel(),
                DateTimePicker::make('phone_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
                TextInput::make('notification_prefs'),
            ]);
    }
}
