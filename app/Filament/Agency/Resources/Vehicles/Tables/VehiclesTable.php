<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Vehicles\Tables;

use App\Enums\VehicleStatus;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('brand.name')
                    ->label('Marque')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('vehicleModel.name')
                    ->label('Modèle')
                    ->searchable(),
                TextColumn::make('year')
                    ->label('Année')
                    ->sortable()
                    ->alignRight(),
                TextColumn::make('mileage_km')
                    ->label('Km')
                    ->numeric(thousandsSeparator: ' ')
                    ->sortable()
                    ->alignRight(),
                TextColumn::make('price_mru')
                    ->label('Prix')
                    ->money('MRU', divideBy: 1)
                    ->sortable()
                    ->alignRight(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(function ($state): string {
                        $value = $state instanceof \BackedEnum ? $state->value : (string) $state;
                        return match ($value) {
                            'active' => 'success',
                            'pending' => 'warning',
                            'rejected' => 'danger',
                            'sold' => 'info',
                            'expired' => 'gray',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state): string {
                        $value = $state instanceof \BackedEnum ? $state->value : (string) $state;
                        return match ($value) {
                            'active' => 'Active',
                            'pending' => 'En attente',
                            'rejected' => 'Refusée',
                            'sold' => 'Vendue',
                            'expired' => 'Expirée',
                            'draft' => 'Brouillon',
                            default => $value,
                        };
                    }),
                TextColumn::make('views_count')
                    ->label('Vues')
                    ->badge()
                    ->color('gray')
                    ->alignRight(),
                TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->badge()
                    ->color('success')
                    ->alignRight(),
                TextColumn::make('published_at')
                    ->label('Publié le')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending' => 'En attente',
                        'rejected' => 'Refusée',
                        'sold' => 'Vendue',
                        'expired' => 'Expirée',
                    ]),
            ])
            ->recordActions([
                Action::make('sold')
                    ->label('Marquer vendue')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Marquer comme vendue ?')
                    ->modalDescription('Cette annonce sera masquee du site.')
                    ->visible(fn ($record) => $record->status !== VehicleStatus::SOLD)
                    ->action(function ($record) {
                        $record->update(['status' => VehicleStatus::SOLD]);
                        Notification::make()->title('Marque comme vendu')->success()->send();
                    }),
                Action::make('reduce_price')
                    ->label('Reduire le prix')
                    ->icon('heroicon-o-tag')
                    ->color('warning')
                    ->form([
                        TextInput::make('new_price')
                            ->label('Nouveau prix (MRU)')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                        Toggle::make('is_deal')
                            ->label('Badge DEAL')
                            ->default(true),
                    ])
                    ->action(function ($record, array $data) {
                        $originalPrice = $record->original_price ?? $record->price_mru;
                        $record->original_price = $originalPrice;
                        $record->price_mru = (int) $data['new_price'];
                        $record->is_deal = $data['is_deal'];
                        $record->save();
                        Notification::make()->title('Prix mis a jour')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
