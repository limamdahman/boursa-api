<?php

declare(strict_types=1);

namespace App\Actions\Vehicles;

use App\Enums\VehicleStatus;
use App\Models\Agency;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

final class CreateVehicleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Agency $agency, array $data): Vehicle
    {
        return DB::transaction(function () use ($agency, $data): Vehicle {
            $lat = $data['latitude'] ?? null;
            $lng = $data['longitude'] ?? null;
            unset($data['latitude'], $data['longitude']);

            $vehicle = Vehicle::create(array_merge($data, [
                'agency_id' => $agency->id,
                'user_id' => $agency->user_id,
                'status' => VehicleStatus::DRAFT,
            ]));

            if ($lat !== null && $lng !== null) {
                DB::statement(
                    'UPDATE vehicles SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                    [(float) $lng, (float) $lat, $vehicle->id]
                );
            }

            return $vehicle->fresh()->load(['brand', 'vehicleModel', 'city']);
        });
    }
}
