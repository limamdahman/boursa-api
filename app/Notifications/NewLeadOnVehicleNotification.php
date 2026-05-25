<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lead;
use App\Models\Vehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewLeadOnVehicleNotification extends Notification
{
    use Queueable;

    public function __construct(private Lead $lead, private Vehicle $vehicle) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_lead',
            'vehicle_id' => $this->vehicle->id,
            'brand' => $this->vehicle->brand?->name,
            'model' => $this->vehicle->vehicleModel?->name,
            'lead_id' => $this->lead->id,
            'lead_name' => $this->lead->name ?? null,
            'lead_phone' => $this->lead->phone ?? null,
        ];
    }
}
