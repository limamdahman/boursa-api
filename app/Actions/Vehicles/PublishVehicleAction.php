<?php

declare(strict_types=1);

namespace App\Actions\Vehicles;

use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use DomainException;

final class PublishVehicleAction
{
    /**
     * Soumet un véhicule à modération.
     * En MVP: passe direct à ACTIVE si l'agence est verified (à durcir plus tard).
     */
    public function execute(Vehicle $vehicle): Vehicle
    {
        if ($vehicle->status === VehicleStatus::ACTIVE) {
            throw new DomainException('Véhicule déjà publié.');
        }

        if ($vehicle->media()->count() === 0) {
            throw new DomainException('Au moins une photo est requise pour publier.');
        }

        $agency = $vehicle->agency;
        if (! $agency) {
            throw new DomainException('Agence introuvable.');
        }

        $isVerified = $agency->isVerified();

        $vehicle->update([
            'status' => $isVerified ? VehicleStatus::ACTIVE : VehicleStatus::PENDING,
            'published_at' => $isVerified ? now() : null,
            'expires_at' => $isVerified ? now()->addMonths(2) : null,
        ]);

        return $vehicle->fresh();
    }
}
