<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Vehicles\Pages;

use App\Filament\Agency\Resources\Vehicles\VehicleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nouvelle annonce')
                ->icon('heroicon-o-plus'),
        ];
    }
}
