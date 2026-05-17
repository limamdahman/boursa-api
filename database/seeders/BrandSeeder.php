<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            // Top tier MR
            ['name' => 'Toyota', 'slug' => 'toyota', 'sort_order' => 1],
            ['name' => 'Hyundai', 'slug' => 'hyundai', 'sort_order' => 2],
            ['name' => 'Nissan', 'slug' => 'nissan', 'sort_order' => 3],
            ['name' => 'Kia', 'slug' => 'kia', 'sort_order' => 4],
            ['name' => 'Mercedes-Benz', 'slug' => 'mercedes-benz', 'sort_order' => 5],
            ['name' => 'Mitsubishi', 'slug' => 'mitsubishi', 'sort_order' => 6],
            ['name' => 'Renault', 'slug' => 'renault', 'sort_order' => 7],
            ['name' => 'Peugeot', 'slug' => 'peugeot', 'sort_order' => 8],
            ['name' => 'Volkswagen', 'slug' => 'volkswagen', 'sort_order' => 9],
            ['name' => 'Ford', 'slug' => 'ford', 'sort_order' => 10],

            // Premium
            ['name' => 'BMW', 'slug' => 'bmw', 'sort_order' => 11],
            ['name' => 'Audi', 'slug' => 'audi', 'sort_order' => 12],
            ['name' => 'Land Rover', 'slug' => 'land-rover', 'sort_order' => 13],
            ['name' => 'Lexus', 'slug' => 'lexus', 'sort_order' => 14],

            // Pickup / utilitaires
            ['name' => 'Isuzu', 'slug' => 'isuzu', 'sort_order' => 15],
            ['name' => 'Mazda', 'slug' => 'mazda', 'sort_order' => 16],
            ['name' => 'Suzuki', 'slug' => 'suzuki', 'sort_order' => 17],
            ['name' => 'Honda', 'slug' => 'honda', 'sort_order' => 18],

            // Chinois (montée en puissance MR)
            ['name' => 'Chery', 'slug' => 'chery', 'sort_order' => 19],
            ['name' => 'Geely', 'slug' => 'geely', 'sort_order' => 20],
            ['name' => 'BYD', 'slug' => 'byd', 'sort_order' => 21],

            // Autres
            ['name' => 'Fiat', 'slug' => 'fiat', 'sort_order' => 22],
            ['name' => 'Citroën', 'slug' => 'citroen', 'sort_order' => 23],
            ['name' => 'Opel', 'slug' => 'opel', 'sort_order' => 24],
            ['name' => 'Dacia', 'slug' => 'dacia', 'sort_order' => 25],
        ];

        foreach ($brands as $brand) {
            Brand::updateOrCreate(['slug' => $brand['slug']], $brand);
        }

        $this->command->info(sprintf('  Inserted %d brands', count($brands)));
    }
}
