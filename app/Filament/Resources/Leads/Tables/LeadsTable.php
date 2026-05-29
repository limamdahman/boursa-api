<?php

namespace App\Filament\Resources\Leads\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use App\Filament\Admin\Actions\ExportCsvAction;
use App\Models\Lead;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->headerActions([
                ExportCsvAction::make(
                    'leads_' . now()->format('Y-m-d') . '.csv',
                    [
                        'ID'         => 'id',
                        'Agence'     => fn ($r) => $r->agency?->name ?? '',
                        'Véhicule'   => fn ($r) => ($r->vehicle?->brand?->name ?? '') . ' ' . ($r->vehicle?->vehicleModel?->name ?? ''),
                        'Nom'        => 'name',
                        'Téléphone'  => 'phone',
                        'Message'    => 'message',
                        'Type'       => 'type',
                        'Date'       => fn ($r) => $r->created_at?->format('d/m/Y H:i') ?? '',
                    ],
                    Lead::with(['agency', 'vehicle.brand', 'vehicle.vehicleModel'])->latest()
                ),
            ])
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
