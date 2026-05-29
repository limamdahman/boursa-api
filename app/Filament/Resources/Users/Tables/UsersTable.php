<?php
declare(strict_types=1);
namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_object($state) ? $state->value : $state)
                    ->color(fn ($state): string => match (is_object($state) ? $state->value : $state) {
                        'admin'   => 'danger',
                        'agency'  => 'warning',
                        default   => 'gray',
                    }),
                IconColumn::make('phone_verified_at')
                    ->label('Tél. vérifié')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->phone_verified_at !== null),
                IconColumn::make('email_verified_at')
                    ->label('Email vérifié')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->email_verified_at !== null),
                TextColumn::make('last_seen_at')
                    ->label('Dernière activité')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'user'   => 'Utilisateur',
                        'agency' => 'Agence',
                        'admin'  => 'Admin',
                    ]),
                TrashedFilter::make()
                    ->label('Bannis'),
            ])
            ->recordActions([
                Action::make('verify_phone')
                    ->label('Vérifier tél.')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (User $record): bool => $record->phone_verified_at === null)
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->update(['phone_verified_at' => now()]);
                        Notification::make()
                            ->title('Téléphone vérifié')
                            ->success()
                            ->send();
                    }),
                Action::make('ban')
                    ->label('Bannir')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->deleted_at === null)
                    ->requiresConfirmation()
                    ->modalHeading('Bannir cet utilisateur ?')
                    ->modalDescription('L\'utilisateur ne pourra plus se connecter.')
                    ->action(function (User $record) {
                        $record->delete(); // soft delete = ban
                        Notification::make()
                            ->title('Utilisateur banni')
                            ->danger()
                            ->send();
                    }),
                Action::make('unban')
                    ->label('Débannir')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (User $record): bool => $record->deleted_at !== null)
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $record->restore();
                        Notification::make()
                            ->title('Utilisateur débanni')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Bannir sélection'),
                    ForceDeleteBulkAction::make()->label('Supprimer définitivement'),
                    RestoreBulkAction::make()->label('Débannir sélection'),
                ]),
            ]);
    }
}
