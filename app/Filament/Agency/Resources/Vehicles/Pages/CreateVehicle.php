<?php

declare(strict_types=1);

namespace App\Filament\Agency\Resources\Vehicles\Pages;

use App\Enums\VehicleStatus;
use App\Filament\Agency\Resources\Vehicles\VehicleResource;
use App\Services\Media\MediaUploadService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CreateVehicle extends CreateRecord
{
    protected static string $resource = VehicleResource::class;

    /** @var array<string> */
    protected array $uploadedPhotos = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Récupérer les photos avant qu'elles soient retirées (dehydrated false)
        $this->uploadedPhotos = $data['photos'] ?? [];
        unset($data['photos']);

        $data['agency_id'] = auth()->user()->agency->id;
        $data['status'] = VehicleStatus::PENDING->value;
        $data['currency'] = 'MRU';

        return $data;
    }

    protected function afterCreate(): void
    {
        if (empty($this->uploadedPhotos)) {
            return;
        }

        $service = app(MediaUploadService::class);
        $count = 0;

        foreach ($this->uploadedPhotos as $index => $tmpPath) {
            try {
                $fullPath = Storage::disk('local')->path($tmpPath);
                if (! file_exists($fullPath)) {
                    continue;
                }

                $uploadedFile = new UploadedFile(
                    $fullPath,
                    basename($tmpPath),
                    mime_content_type($fullPath) ?: 'image/jpeg',
                    null,
                    true
                );

                $service->store($this->record, $uploadedFile, $index === 0);
                $count++;

                Storage::disk('local')->delete($tmpPath);
            } catch (\Throwable $e) {
                // log silently
            }
        }

        if ($count > 0) {
            Notification::make()
                ->success()
                ->title("Annonce créée avec {$count} photo(s)")
                ->body('Le watermark et les thumbnails se génèrent en arrière-plan.')
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
