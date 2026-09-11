<?php

declare(strict_types=1);

namespace App\Filament\Resources\AiRuns\Schemas;

use App\Enums\AiAgent;
use App\Enums\AiRunStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AiRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subject_type'),
                TextInput::make('subject_id')
                    ->numeric(),
                Select::make('user_id')
                    ->relationship('user', 'name'),
                Select::make('agent')
                    ->options(AiAgent::class)
                    ->required(),
                TextInput::make('task')
                    ->required(),
                TextInput::make('model'),
                TextInput::make('input')
                    ->required(),
                TextInput::make('output'),
                TextInput::make('confidence')
                    ->numeric(),
                TextInput::make('tokens_in')
                    ->numeric(),
                TextInput::make('tokens_out')
                    ->numeric(),
                TextInput::make('cost_cents')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('latency_ms')
                    ->numeric(),
                Select::make('status')
                    ->options(AiRunStatus::class)
                    ->default('queued')
                    ->required(),
                Textarea::make('error')
                    ->columnSpanFull(),
                TextInput::make('cache_key'),
            ]);
    }
}
