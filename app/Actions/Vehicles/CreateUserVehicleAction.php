<?php

declare(strict_types=1);

namespace App\Actions\Vehicles;

use App\Enums\VehicleStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

final class CreateUserVehicleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $user, array $data): Vehicle
    {
        return DB::transaction(function () use ($user, $data): Vehicle {
            $lat = $data['latitude'] ?? null;
            $lng = $data['longitude'] ?? null;
            unset($data['latitude'], $data['longitude']);

            // Particulier : pas d'agency, status PENDING par défaut (modération)
            $vehicle = Vehicle::create(array_merge($data, [
                'user_id' => $user->id,
                'agency_id' => null,
                'status' => VehicleStatus::PENDING,
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
