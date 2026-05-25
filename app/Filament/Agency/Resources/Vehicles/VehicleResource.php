<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Vehicles;

use App\Filament\Agency\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Agency\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Agency\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Agency\Resources\Vehicles\Schemas\VehicleForm;
use App\Filament\Agency\Resources\Vehicles\Tables\VehiclesTable;
use App\Filament\Resources\Vehicles\RelationManagers\MediaRelationManager;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static \UnitEnum|string|null $navigationGroup = 'Mes annonces';

    protected static ?string $modelLabel = 'Annonce';

    protected static ?string $pluralModelLabel = 'Annonces';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    public static function form(Schema $schema): Schema
    {
        return VehicleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehiclesTable::configure($table);
    }

    /**
     * SCOPING SÉCURITÉ : ne montre QUE les véhicules de l'agence du user connecté.
     * Garde-fou critique : si pas d'agency, ne retourne RIEN.
     */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $agencyId = $user?->agency?->id;

        $query = parent::getEloquentQuery();

        if (! $agencyId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('vehicles.agency_id', $agencyId);
    }

    public static function getRelations(): array
    {
        return [
            MediaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
