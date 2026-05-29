<?php

namespace App\Filament\Resources\Agencies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use App\Filament\Admin\Actions\ExportCsvAction;
use App\Models\Agency;
use Filament\Tables\Table;

class AgenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->headerActions([
                ExportCsvAction::make(
                    'agences_' . now()->format('Y-m-d') . '.csv',
                    [
                        'ID'           => 'id',
                        'Nom'          => 'name',
                        'Email'        => 'email',
                        'Téléphone'    => 'phone',
                        'Ville'        => fn ($r) => $r->city?->name_fr ?? '',
                        'Abonnement'   => 'subscription_tier',
                        'Fin abonnement' => fn ($r) => $r->subscription_end?->format('d/m/Y') ?? '',
                        'Véhicules actifs' => fn ($r) => $r->vehicles()->where('status', 'active')->count(),
                        'Vérifié'      => fn ($r) => $r->verified_at ? 'Oui' : 'Non',
                        'Inscription'  => fn ($r) => $r->created_at?->format('d/m/Y') ?? '',
                    ],
                    Agency::with(['city'])->latest()
                ),
            ])
            ->columns([
                TextColumn::make('id')
                    ->label('ID'),
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('logo_url')
                    ->searchable(),
                TextColumn::make('address')
                    ->searchable(),
                TextColumn::make('city.id')
                    ->searchable(),
                TextColumn::make('rc_number')
                    ->searchable(),
                TextColumn::make('phone_whatsapp')
                    ->searchable(),
                TextColumn::make('phone_call')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('website')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('subscription_tier')
                    ->searchable(),
                TextColumn::make('quota_active_listings')
                    ->numeric()
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
                TrashedFilter::make(),
            ])
            ->recordActions([
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
