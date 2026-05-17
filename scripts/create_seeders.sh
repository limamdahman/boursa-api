#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SEEDERS="$PROJECT_ROOT/database/seeders"
cd "$PROJECT_ROOT"

echo "==> Génération des seeders Boursa"

# --- CitySeeder ---
cat > "$SEEDERS/CitySeeder.php" << 'EOF'
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['Nouakchott', 'نواكشوط', 'nouakchott', 'Nouakchott', -15.9785, 18.0735, 1],
            ['Nouadhibou', 'نواذيبو', 'nouadhibou', 'Dakhlet Nouadhibou', -17.0381, 20.9410, 2],
            ['Rosso', 'روصو', 'rosso', 'Trarza', -15.8056, 16.5138, 3],
            ['Kaédi', 'كيهيدي', 'kaedi', 'Gorgol', -13.5072, 16.1500, 4],
            ['Zouérat', 'ازويرات', 'zouerat', 'Tiris Zemmour', -12.4682, 22.7351, 5],
            ['Atar', 'أطار', 'atar', 'Adrar', -13.0500, 20.5167, 6],
            ['Néma', 'النعمة', 'nema', 'Hodh Ech Chargui', -7.2589, 16.6172, 7],
            ['Aioun', 'العيون', 'aioun', 'Hodh El Gharbi', -9.6131, 16.6593, 8],
            ['Kiffa', 'كيفه', 'kiffa', 'Assaba', -11.4044, 16.6203, 9],
            ['Sélibaby', 'سيلبابي', 'selibaby', 'Guidimaka', -12.1833, 15.1667, 10],
            ['Tidjikja', 'تجكجة', 'tidjikja', 'Tagant', -11.4297, 18.5667, 11],
            ['Aleg', 'ألاك', 'aleg', 'Brakna', -13.9111, 17.0500, 12],
            ['Boutilimit', 'بوتلميت', 'boutilimit', 'Trarza', -14.6877, 17.5530, 13],
            ['Akjoujt', 'أكجوجت', 'akjoujt', 'Inchiri', -14.3833, 19.7333, 14],
        ];

        // Quartiers de Nouakchott (sous-zones utiles pour la recherche)
        $quarters = [
            ['Tevragh Zeina', 'تفرغ زينة', 'tevragh-zeina-nkc', 'Nouakchott', -15.9890, 18.1010, 20],
            ['Ksar', 'القصر', 'ksar-nkc', 'Nouakchott', -15.9620, 18.0830, 21],
            ['Sebkha', 'السبخة', 'sebkha-nkc', 'Nouakchott', -16.0000, 18.0600, 22],
            ['Arafat', 'عرفات', 'arafat-nkc', 'Nouakchott', -15.9700, 18.0500, 23],
            ['Toujounine', 'توجنين', 'toujounine-nkc', 'Nouakchott', -15.9350, 18.1130, 24],
            ['Dar Naïm', 'دار النعيم', 'dar-naim-nkc', 'Nouakchott', -15.9290, 18.1280, 25],
            ['El Mina', 'الميناء', 'el-mina-nkc', 'Nouakchott', -16.0200, 18.0440, 26],
            ['Riyad', 'الرياض', 'riyad-nkc', 'Nouakchott', -15.9430, 18.0560, 27],
            ['Teyarett', 'تيارت', 'teyarett-nkc', 'Nouakchott', -15.9510, 18.1090, 28],
        ];

        DB::table('cities')->truncate();

        foreach (array_merge($cities, $quarters) as [$nameFr, $nameAr, $slug, $region, $lng, $lat, $sortOrder]) {
            DB::insert("
                INSERT INTO cities (name_fr, name_ar, slug, region, location, is_active, sort_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, true, ?, NOW(), NOW())
            ", [$nameFr, $nameAr, $slug, $region, $lng, $lat, $sortOrder]);
        }

        $this->command->info(sprintf('  Inserted %d cities', count($cities) + count($quarters)));
    }
}
EOF

# --- BrandSeeder ---
cat > "$SEEDERS/BrandSeeder.php" << 'EOF'
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
EOF

# --- VehicleModelSeeder ---
cat > "$SEEDERS/VehicleModelSeeder.php" << 'EOF'
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\VehicleModel;
use Illuminate\Database\Seeder;

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
                $slug = \Illuminate\Support\Str::slug($modelName);
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
EOF

# --- AdminUserSeeder ---
cat > "$SEEDERS/AdminUserSeeder.php" << 'EOF'
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '22244000001'],
            [
                'name' => 'Boursa Admin',
                'email' => 'admin@boursa.mr',
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
                'password' => Hash::make('admin1234'),
                'role' => UserRole::ADMIN,
                'language' => 'fr',
            ]
        );

        $this->command->info('  Admin user created (phone: 22244000001 / password: admin1234)');
    }
}
EOF

# --- DatabaseSeeder (orchestrateur) ---
cat > "$SEEDERS/DatabaseSeeder.php" << 'EOF'
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CitySeeder::class,
            BrandSeeder::class,
            VehicleModelSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
EOF

echo "==> ✅ Seeders générés"
ls -la "$SEEDERS"
