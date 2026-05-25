<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Vehicles\Pages;

use App\Enums\VehicleStatus;
use App\Filament\Agency\Resources\Vehicles\VehicleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Retirer photos (gérées par RelationManager)
        unset($data['photos']);

        if ($this->record->status?->value === 'rejected') {
            $data['status'] = VehicleStatus::PENDING->value;
            $data['moderation_notes'] = null;
        }
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
