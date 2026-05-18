<?php

declare(strict_types=1);

namespace App\Actions\Vehicles;

use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

final class UpdateVehicleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Vehicle $vehicle, array $data): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $data): Vehicle {
            $lat = $data['latitude'] ?? null;
            $lng = $data['longitude'] ?? null;
            unset($data['latitude'], $data['longitude']);

            if (! empty($data)) {
                $vehicle->update($data);
            }

            if ($lat !== null && $lng !== null) {
                DB::statement(
                    'UPDATE vehicles SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                    [(float) $lng, (float) $lat, $vehicle->id]
                );
            }

            return $vehicle->fresh()->load(['brand', 'vehicleModel', 'city', 'media']);
        });
    }
}
