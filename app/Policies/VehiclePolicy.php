<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->isOwner($user, $vehicle);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->isOwner($user, $vehicle);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->isOwner($user, $vehicle);
    }

    public function publish(User $user, Vehicle $vehicle): bool
    {
        return $this->isOwner($user, $vehicle);
    }

    public function manageMedia(User $user, Vehicle $vehicle): bool
    {
        return $this->isOwner($user, $vehicle);
    }

    private function isOwner(User $user, Vehicle $vehicle): bool
    {
        $agencyId = $user->agency?->id;

        return $agencyId !== null && $vehicle->agency_id === $agencyId;
    }
}
