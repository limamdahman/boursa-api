<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Vehicle;
use App\Services\Pricing\PriceRatingService;

final class VehicleObserver
{
    public function __construct(private readonly PriceRatingService $priceRating)
    {
    }

    /**
     * Recalcule le price_rating si le prix change ou si le véhicule devient actif.
     */
    public function saved(Vehicle $vehicle): void
    {
        // Éviter boucle infinie : saveQuietly() du service ne déclenche pas saved
        $relevantChanged = $vehicle->wasChanged([
            'price_mru', 'year', 'mileage_km', 'fuel', 'transmission',
            'brand_id', 'vehicle_model_id', 'status',
        ]);

        if (! $relevantChanged && $vehicle->price_rating !== null) {
            return;
        }

        if ($vehicle->status?->value !== 'active') {
            return;
        }

        try {
            $this->priceRating->rate($vehicle);
        } catch (\Throwable $e) {
            \Log::error('Failed to compute price rating', [
                'vehicle_id' => $vehicle->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
