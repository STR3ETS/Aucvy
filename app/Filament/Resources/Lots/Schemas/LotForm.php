<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lots\Schemas;

use App\Enums\LotCondition;
use App\Enums\LotStatus;
use App\Enums\SaleType;
use App\Enums\ValueConfidence;
use App\Enums\VatRegime;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class LotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('public_id')
                    ->required(),
                Select::make('seller_id')
                    ->relationship('seller', 'name'),
                Select::make('company_id')
                    ->relationship('company', 'name'),
                Select::make('category_id')
                    ->relationship('category', 'name'),
                Select::make('location_id')
                    ->relationship('location', 'id'),
                TextInput::make('project_id')
                    ->numeric(),
                TextInput::make('title'),
                TextInput::make('slug'),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('brand'),
                TextInput::make('model'),
                TextInput::make('year')
                    ->numeric(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(1),
                Select::make('condition')
                    ->options(LotCondition::class),
                TextInput::make('specifications'),
                Select::make('status')
                    ->options(LotStatus::class)
                    ->default('draft')
                    ->required(),
                Select::make('sale_type')
                    ->options(SaleType::class),
                Select::make('vat_regime')
                    ->options(VatRegime::class)
                    ->default('margin')
                    ->required(),
                TextInput::make('completeness_score')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('value_min_cents')
                    ->numeric(),
                TextInput::make('value_max_cents')
                    ->numeric(),
                Select::make('value_confidence')
                    ->options(ValueConfidence::class),
                Toggle::make('pickup_only')
                    ->required(),
                Toggle::make('viewing_available')
                    ->required(),
                TextInput::make('transport_from_cents')
                    ->numeric(),
                TextInput::make('draft_meta'),
                DateTimePicker::make('published_at'),
            ]);
    }
}
