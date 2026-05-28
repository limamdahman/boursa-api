<?php
declare(strict_types=1);
namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\AgencyResource\Pages;
use App\Models\Agency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AgencyResource extends Resource
{
    protected static ?string $model = Agency::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Agences';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nom')->required(),
            Forms\Components\Select::make('subscription_tier')
                ->label('Abonnement')
                ->options(['free' => 'Gratuit', 'pro' => 'Pro', 'business' => 'Business'])
                ->required(),
            Forms\Components\Select::make('subscription_months')
                ->label('Durée (mois)')
                ->options(array_combine(range(1, 24), array_map(fn($m) => $m . ' mois', range(1, 24))))
                ->default(1)
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set) {
                    if ($state) {
                        $set('subscription_start', now()->format('Y-m-d H:i:s'));
                        $set('subscription_end', now()->addMonths((int)$state)->format('Y-m-d H:i:s'));
                    }
                })
                ->dehydrated(false),
            Forms\Components\DateTimePicker::make('subscription_start')->label('Début abonnement'),
            Forms\Components\DateTimePicker::make('subscription_end')->label('Fin abonnement'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Agence')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('subscription_tier')
                    ->label('Tier')
                    ->colors(['secondary' => 'free', 'warning' => 'pro', 'success' => 'business']),
                Tables\Columns\TextColumn::make('subscription_end')
                    ->label('Expiration')
                    ->dateTime('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vehicles_count')
                    ->label('Véhicules')
                    ->counts('vehicles'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('subscription_tier')
                    ->options(['free' => 'Gratuit', 'pro' => 'Pro', 'business' => 'Business'])
                    ->label('Tier'),
            ])
            ->actions([
                Tables\Actions\Action::make('upgrade')
                    ->label('Upgrade')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('tier')
                            ->label('Nouveau tier')
                            ->options(['pro' => 'Pro', 'business' => 'Business', 'free' => 'Gratuit'])
                            ->required(),
                        Forms\Components\Select::make('months')
                            ->label('Durée')
                            ->options(array_combine(range(1, 24), array_map(fn($m) => $m . ' mois', range(1, 24))))
                            ->default(1)
                            ->required(),
                    ])
                    ->action(function (Agency $record, array $data) {
                        $start = now();
                        $end = now()->addMonths((int)$data['months']);
                        $record->update([
                            'subscription_tier'  => $data['tier'],
                            'subscription_start' => $start,
                            'subscription_end'   => $end,
                        ]);
                        \Filament\Notifications\Notification::make()
                            ->title('Abonnement mis à jour : ' . strtoupper($data['tier']) . ' — ' . $data['months'] . ' mois')
                            ->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgencies::route('/'),
            'edit'  => Pages\EditAgency::route('/{record}/edit'),
        ];
    }
}
