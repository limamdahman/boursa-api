<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VehicleModelSeeder extends Seeder
{
    public function run(): void
    {
        $modelsByBrand = [
            'toyota' => ['Hilux', 'Land Cruiser', 'Prado', 'RAV4', 'Corolla', 'Camry', 'Yaris', 'Hiace', 'Avensis', 'Auris', 'Fortuner'],
            'hyundai' => ['Tucson', 'Santa Fe', 'Elantra', 'Accent', 'i10', 'i20', 'i30', 'Sonata', 'Creta', 'H1'],
            'nissan' => ['Patrol', 'Pathfinder', 'Qashqai', 'Sentra', 'Sunny', 'Micra', 'X-Trail', 'Navara', 'Almera', 'Juke'],
            'kia' => ['Sportage', 'Sorento', 'Picanto', 'Rio', 'Cerato', 'Optima', 'Carnival'],
            'mercedes-benz' => ['Classe A', 'Classe C', 'Classe E', 'Classe S', 'GLA', 'GLC', 'GLE', 'GLS', 'Sprinter', 'Vito'],
            'mitsubishi' => ['Pajero', 'L200', 'Outlander', 'Lancer', 'ASX', 'Eclipse Cross'],
            'renault' => ['Clio', 'Megane', 'Logan', 'Sandero', 'Duster', 'Captur', 'Kangoo', 'Trafic'],
            'peugeot' => ['208', '308', '508', '2008', '3008', '5008', 'Partner', 'Boxer'],
            'volkswagen' => ['Golf', 'Polo', 'Passat', 'Tiguan', 'Touareg', 'Caddy', 'Transporter'],
            'ford' => ['Ranger', 'Focus', 'Fiesta', 'Kuga', 'Explorer', 'Transit', 'Escape'],
            'bmw' => ['Série 1', 'Série 3', 'Série 5', 'Série 7', 'X1', 'X3', 'X5', 'X6'],
            'audi' => ['A3', 'A4', 'A6', 'Q3', 'Q5', 'Q7'],
            'land-rover' => ['Range Rover', 'Range Rover Sport', 'Discovery', 'Defender'],
            'lexus' => ['RX', 'NX', 'IS', 'ES', 'LX'],
            'isuzu' => ['D-Max', 'MU-X', 'NPR', 'Trooper'],
            'mazda' => ['CX-5', 'CX-3', 'Mazda 3', 'Mazda 6', 'BT-50'],
            'suzuki' => ['Swift', 'Vitara', 'Jimny', 'Baleno', 'Alto'],
            'honda' => ['Civic', 'Accord', 'CR-V', 'HR-V', 'Pilot'],
            'chery' => ['Tiggo 4', 'Tiggo 7', 'Tiggo 8', 'Arrizo 5'],
            'geely' => ['Emgrand', 'Coolray', 'Atlas'],
            'byd' => ['F3', 'Song', 'Tang', 'Atto 3'],
            'fiat' => ['Punto', 'Tipo', '500', 'Doblo'],
            'citroen' => ['C3', 'C4', 'C5', 'Berlingo', 'Jumper'],
            'opel' => ['Astra', 'Corsa', 'Insignia', 'Mokka'],
            'dacia' => ['Logan', 'Sandero', 'Duster', 'Dokker'],
        ];

        $total = 0;
        foreach ($modelsByBrand as $brandSlug => $models) {
            $brand = Brand::where('slug', $brandSlug)->first();
            if (! $brand) {
                continue;
            }

            foreach ($models as $modelName) {
                $slug = Str::slug($modelName);
                VehicleModel::updateOrCreate(
                    ['brand_id' => $brand->id, 'slug' => $slug],
                    ['name' => $modelName, 'is_active' => true]
                );
                $total++;
            }
        }

        $this->command->info(sprintf('  Inserted %d vehicle models', $total));
    }
}
