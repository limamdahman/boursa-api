<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VehicleRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(private Vehicle $vehicle, private ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'vehicle_rejected',
            'vehicle_id' => $this->vehicle->id,
            'brand' => $this->vehicle->brand?->name,
            'model' => $this->vehicle->vehicleModel?->name,
            'year' => $this->vehicle->year,
            'reason' => $this->reason,
        ];
    }
}
