<?php

declare(strict_types=1);

namespace App\Filament\Agency\Widgets;

use App\Models\Vehicle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopVehiclesWidget extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = auth()->user();
        $agencyId = $user?->agency?->id;

        return $table
            ->heading('Vos 5 voitures les plus vues')
            ->query(
                Vehicle::query()
                    ->where('agency_id', $agencyId ?? '00000000-0000-0000-0000-000000000000')
                    ->with(['brand', 'vehicleModel'])
                    ->orderByDesc('views_count')
                    ->limit(5)
            )
            ->columns([
                TextColumn::make('brand.name')
                    ->label('Marque')
                    ->weight('bold'),
                TextColumn::make('vehicleModel.name')
                    ->label('Modèle'),
                TextColumn::make('year')
                    ->label('Année')
                    ->alignRight(),
                TextColumn::make('price_mru')
                    ->label('Prix')
                    ->money('MRU', divideBy: 1)
                    ->alignRight(),
                TextColumn::make('views_count')
                    ->label('Vues')
                    ->badge()
                    ->color('success')
                    ->alignRight(),
                TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->badge()
                    ->color('warning')
                    ->alignRight(),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge(),
            ])
            ->paginated(false);
    }
}
