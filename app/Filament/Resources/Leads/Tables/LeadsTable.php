<?php

namespace App\Filament\Resources\Leads\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID'),
                TextColumn::make('vehicle.id')
                    ->searchable(),
                TextColumn::make('agency.name')
                    ->searchable(),
                TextColumn::make('sender_user_id'),
                TextColumn::make('sender_name')
                    ->searchable(),
                TextColumn::make('sender_phone')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                IconColumn::make('is_read')
                    ->boolean(),
                TextColumn::make('read_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ip_address')
                    ->searchable(),
                TextColumn::make('user_agent')
                    ->searchable(),
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
