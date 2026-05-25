<?php

namespace App\Filament\Resources\Vehicles\Tables;

use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use App\Notifications\VehicleApprovedNotification;
use App\Notifications\VehicleRejectedNotification;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID'),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('agency.name')
                    ->searchable(),
                TextColumn::make('brand.name')
                    ->searchable(),
                TextColumn::make('vehicleModel.name')
                    ->searchable(),
                TextColumn::make('year')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('mileage_km')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price_mru')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('price_negotiable')
                    ->boolean(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('fuel')
                    ->searchable(),
                TextColumn::make('transmission')
                    ->searchable(),
                TextColumn::make('body_type')
                    ->searchable(),
                TextColumn::make('color')
                    ->searchable(),
                TextColumn::make('condition')
                    ->searchable(),
                TextColumn::make('city.id')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('moderated_by'),
                TextColumn::make('moderated_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('views_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('contacts_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('location'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'En attente',
                        'active' => 'Active',
                        'rejected' => 'Refusée',
                        'draft' => 'Brouillon',
                        'sold' => 'Vendue',
                        'expired' => 'Expirée',
                    ])
                    ->default('pending'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Approuver')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Vehicle $record): bool => $record->status?->value === 'pending')
                    ->action(function (Vehicle $record) {
                        $record->update([
                            'status' => VehicleStatus::ACTIVE,
                            'published_at' => now(),
                            'expires_at' => now()->addMonths(2),
                            'moderated_at' => now(),
                            'moderated_by' => auth()->id(),
                        ]);
                        // Notifier le propriétaire
                        $owner = $record->agency?->user ?? $record->user;
                        if ($owner) {
                            $owner->notify(new VehicleApprovedNotification($record));

                            // Notif Filament native pour cloche panel agence
                            if ($owner->role?->value === 'agency') {
                                $brand = $record->brand?->name ?? '';
                                $model = $record->vehicleModel?->name ?? '';
                                $year = $record->year ?? '';
                                FilamentNotification::make()
                                    ->title('Annonce approuvée 🎉')
                                    ->body('Votre ' . trim($brand . ' ' . $model . ' ' . $year) . ' a été approuvée et publiée.')
                                    ->icon('heroicon-o-check-circle')
                                    ->iconColor('success')
                                    ->actions([
                                        \Filament\Actions\Action::make('view')
                                            ->label("Voir l'annonce")
                                            ->url('/agence/vehicles/' . $record->id . '/edit')
                                            ->markAsRead(),
                                    ])
                                    ->sendToDatabase($owner);
                            }
                        }
                        FilamentNotification::make()
                            ->title('Annonce approuvée et publiée')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Refuser')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Vehicle $record): bool => $record->status?->value === 'pending')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Raison du refus (optionnel)')
                            ->placeholder('Photos floues, prix incohérent, description incomplète...')
                            ->rows(3),
                    ])
                    ->action(function (Vehicle $record, array $data) {
                        $reason = $data['reason'] ?? null;
                        $record->update([
                            'status' => VehicleStatus::REJECTED,
                            'moderation_notes' => $reason,
                            'moderated_at' => now(),
                            'moderated_by' => auth()->id(),
                        ]);
                        $owner = $record->agency?->user ?? $record->user;
                        if ($owner) {
                            $owner->notify(new VehicleRejectedNotification($record, $reason));

                            // Notif Filament native pour cloche panel agence
                            if ($owner->role?->value === 'agency') {
                                $brand = $record->brand?->name ?? '';
                                $model = $record->vehicleModel?->name ?? '';
                                $year = $record->year ?? '';
                                FilamentNotification::make()
                                    ->title('Annonce refusée')
                                    ->body('Votre ' . trim($brand . ' ' . $model . ' ' . $year) . ' a été refusée. Raison : ' . ($reason ?? 'Non précisée'))
                                    ->icon('heroicon-o-x-circle')
                                    ->iconColor('danger')
                                    ->actions([
                                        \Filament\Actions\Action::make('view')
                                            ->label('Modifier')
                                            ->url('/agence/vehicles/' . $record->id . '/edit')
                                            ->markAsRead(),
                                    ])
                                    ->sendToDatabase($owner);
                            }
                        }
                        FilamentNotification::make()
                            ->title('Annonce refusée')
                            ->danger()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
