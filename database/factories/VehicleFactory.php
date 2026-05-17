<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VehicleStatus;
use App\Models\Brand;
use App\Models\City;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        $brand = Brand::inRandomOrder()->first() ?? Brand::factory()->create();
        $model = VehicleModel::where('brand_id', $brand->id)->inRandomOrder()->first()
            ?? VehicleModel::factory()->for($brand)->create();
        $city = City::inRandomOrder()->first();

        $year = $this->faker->numberBetween(2008, 2025);
        $age = 2026 - $year;
        $basePrice = $this->faker->numberBetween(800_000, 8_500_000);
        $depreciation = max(0.3, 1 - ($age * 0.07));
        $price = (int) round($basePrice * $depreciation, -4);

        $fuel = $this->faker->randomElement(['gasoline', 'diesel', 'diesel', 'diesel', 'hybrid']);
        $bodyType = $this->faker->randomElement(['sedan', 'suv', 'pickup', 'suv', 'pickup', 'hatchback']);
        $transmission = $this->faker->randomElement(['manual', 'automatic', 'automatic']);

        return [
            'brand_id' => $brand->id,
            'vehicle_model_id' => $model->id,
            'year' => $year,
            'mileage_km' => $this->faker->numberBetween(15_000, 280_000),
            'price_mru' => $price,
            'price_negotiable' => $this->faker->boolean(70),
            'currency' => 'MRU',
            'fuel' => $fuel,
            'transmission' => $transmission,
            'body_type' => $bodyType,
            'color' => $this->faker->randomElement(['Blanc', 'Noir', 'Gris', 'Argent', 'Beige', 'Rouge', 'Bleu']),
            'condition' => $this->faker->randomElement(['used', 'used', 'used', 'imported']),
            'description_fr' => sprintf(
                '%s %s %d en très bon état. %s, %s. Première main, entretien régulier. Documents en règle.',
                $brand->name,
                $model->name,
                $year,
                $transmission === 'manual' ? 'Boîte manuelle' : 'Boîte automatique',
                $fuel === 'diesel' ? 'diesel' : ($fuel === 'hybrid' ? 'hybride' : 'essence'),
            ),
            'description_ar' => null,
            'city_id' => $city?->id,
            'specs' => [
                'options' => $this->faker->randomElements(
                    ['climatisation', 'jantes_alu', 'gps', 'bluetooth', 'camera_recul', 'sieges_cuir', 'toit_ouvrant', 'abs', 'airbag', 'regulateur'],
                    $this->faker->numberBetween(3, 7)
                ),
                'doors' => $this->faker->randomElement([3, 5]),
                'seats' => $this->faker->randomElement([5, 5, 7]),
            ],
            'status' => VehicleStatus::ACTIVE,
            'views_count' => $this->faker->numberBetween(10, 2000),
            'contacts_count' => $this->faker->numberBetween(0, 80),
            'published_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'expires_at' => now()->addMonths(2),
        ];
    }

    public function configure(): self
    {
        return $this->afterCreating(function (Vehicle $vehicle): void {
            $lat = 18.0735 + $this->faker->randomFloat(4, -0.08, 0.08);
            $lng = -15.9785 + $this->faker->randomFloat(4, -0.08, 0.08);

            DB::statement(
                'UPDATE vehicles SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                [$lng, $lat, $vehicle->id]
            );
        });
    }
}
