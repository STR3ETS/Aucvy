<?php

declare(strict_types=1);

namespace App\Filament\Resources\Auctions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuctionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lot.title')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('original_ends_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('start_price_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reserve_price_cents')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('reserve_met')
                    ->boolean(),
                TextColumn::make('current_price_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bid_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bidder_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('anti_snipe_window_s')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('anti_snipe_extend_s')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('extension_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('buy_now_price_cents')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('buy_now_active')
                    ->boolean(),
                TextColumn::make('dutch_start_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('dutch_floor_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('dutch_step_cents')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('dutch_interval_s')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_sequence')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('winner.name')
                    ->searchable(),
                TextColumn::make('winningBid.id')
                    ->searchable(),
                TextColumn::make('closed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
