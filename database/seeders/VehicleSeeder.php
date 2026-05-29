<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Models\Agency;
use App\Models\User;
use App\Models\City;
use App\Services\Pricing\PriceRatingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleSeeder extends Seeder
{
    // Prix de base par marque (MRU) — calibrés marché mauritanien
    private const BRAND_BASE_PRICES = [
        'Toyota'        => ['min' => 1_200_000, 'max' => 8_000_000],
        'Hyundai'       => ['min' => 800_000,   'max' => 5_000_000],
        'Nissan'        => ['min' => 900_000,   'max' => 7_000_000],
        'Kia'           => ['min' => 700_000,   'max' => 4_500_000],
        'Mercedes-Benz' => ['min' => 2_500_000, 'max' => 15_000_000],
        'Mitsubishi'    => ['min' => 1_000_000, 'max' => 6_000_000],
        'Renault'       => ['min' => 500_000,   'max' => 3_000_000],
        'Peugeot'       => ['min' => 500_000,   'max' => 3_500_000],
        'Volkswagen'    => ['min' => 800_000,   'max' => 5_000_000],
        'Ford'          => ['min' => 900_000,   'max' => 5_500_000],
        'BMW'           => ['min' => 2_000_000, 'max' => 12_000_000],
        'Audi'          => ['min' => 1_800_000, 'max' => 10_000_000],
        'Land Rover'    => ['min' => 3_000_000, 'max' => 18_000_000],
        'Lexus'         => ['min' => 2_500_000, 'max' => 10_000_000],
        'Isuzu'         => ['min' => 1_200_000, 'max' => 5_000_000],
        'Mazda'         => ['min' => 700_000,   'max' => 4_000_000],
        'Suzuki'        => ['min' => 400_000,   'max' => 2_500_000],
        'Honda'         => ['min' => 700_000,   'max' => 4_500_000],
        'Chery'         => ['min' => 600_000,   'max' => 3_000_000],
        'Geely'         => ['min' => 500_000,   'max' => 2_500_000],
        'BYD'           => ['min' => 700_000,   'max' => 4_000_000],
        'Fiat'          => ['min' => 350_000,   'max' => 2_000_000],
        'Citroën'       => ['min' => 400_000,   'max' => 2_500_000],
        'Opel'          => ['min' => 450_000,   'max' => 2_500_000],
        'Dacia'         => ['min' => 350_000,   'max' => 1_800_000],
    ];

    private const FUELS        = ['diesel', 'essence', 'hybride', 'electrique'];
    private const FUEL_WEIGHTS = [50, 35, 10, 5]; // % probabilité
    private const TRANSMISSIONS = ['manual', 'automatic'];
    private const BODY_TYPES   = ['suv', 'sedan', 'pickup', 'van', 'hatchback', 'coupe', 'minivan'];
    private const COLORS       = ['Blanc', 'Noir', 'Gris', 'Argent', 'Rouge', 'Bleu', 'Beige', 'Vert', 'Marron', 'Or'];
    private const CONDITIONS   = ['used', 'used', 'used', 'used', 'new']; // 80% occasion

    private const DESCRIPTIONS_FR = [
        'Véhicule en excellent état, bien entretenu. Idéal pour la ville et les longues distances.',
        'Voiture de particulier, toujours entretenue chez le concessionnaire. Prix ferme.',
        'Très bon état général. Révision récente effectuée. Pneus neufs.',
        'Véhicule importé, dédouané. Aucun accident. Premier propriétaire.',
        'Bon état, moteur impeccable. Quelques petites rayures sans importance.',
        'Voiture familiale spacieuse. Climatisation, vitres électriques, GPS.',
        'SUV puissant, idéal pour les pistes mauritaniennes. 4x4 fonctionnel.',
        'Pick-up robuste, parfait pour le transport et les terrains difficiles.',
        'Économique en carburant. Idéal pour les petits budgets.',
        'Luxueux et confortable. Intérieur cuir, toit ouvrant, caméra de recul.',
    ];

    public function run(): void
    {
        $this->command->info('Chargement des données de référence...');

        $brands    = Brand::with('models')->where('is_active', true)->get()->keyBy('name');
        $agencies  = Agency::pluck('id')->toArray();
        $userIds   = User::where('role', 'user')->pluck('id')->toArray();
        $cityIds   = City::pluck('id')->toArray();

        if ($brands->isEmpty()) {
            $this->command->error('Aucune marque trouvée. Lance BrandSeeder d\'abord.');
            return;
        }

        if (empty($cityIds)) {
            $this->command->error('Aucune ville trouvée.');
            return;
        }

        $this->command->info('Génération de 1000 véhicules...');
        $bar = $this->command->getOutput()->createProgressBar(1000);
        $bar->start();

        $created = 0;
        $target  = 1000;

        // Répartition : ~60% agences, ~40% particuliers
        $useAgency = !empty($agencies);

        DB::transaction(function () use (
            $brands, $agencies, $userIds, $cityIds,
            $useAgency, $target, $bar, &$created
        ) {
            while ($created < $target) {
                // Choisit une marque (pondérée : Toyota/Hyundai/Nissan plus fréquents)
                $brandName = $this->pickBrand($brands->keys()->toArray());
                if (!$brands->has($brandName)) continue;

                $brand  = $brands->get($brandName);
                $models = $brand->models;
                if ($models->isEmpty()) continue;

                $model = $models->random();
                $year  = rand(2008, 2024);

                $basePrice = self::BRAND_BASE_PRICES[$brandName]
                    ?? ['min' => 500_000, 'max' => 4_000_000];

                // Prix ajusté à l'année (plus récent = plus cher)
                $yearFactor = 1 + (($year - 2008) / 16) * 0.8;
                $minP = (int) ($basePrice['min'] * $yearFactor * 0.8);
                $maxP = (int) ($basePrice['max'] * $yearFactor * 1.2);
                $price = $this->roundPrice(rand($minP, $maxP));

                // Kilométrage cohérent avec l'année
                $age        = 2025 - $year;
                $kmPerYear  = rand(12_000, 25_000);
                $mileage    = min($age * $kmPerYear + rand(-10_000, 20_000), 400_000);
                $mileage    = max(0, $mileage);

                $fuel         = $this->pickWeighted(self::FUELS, self::FUEL_WEIGHTS);
                $transmission = rand(0, 1) ? 'automatic' : 'manual';
                $bodyType     = $this->pickBodyType($brandName, $model->name);
                $color        = self::COLORS[array_rand(self::COLORS)];
                $condition    = self::CONDITIONS[array_rand(self::CONDITIONS)];
                $cityId       = $cityIds[array_rand($cityIds)];

                // 60% agence, 40% particulier
                $isAgency = $useAgency && (rand(1, 10) <= 6);
                $agencyId = $isAgency && !empty($agencies) ? $agencies[array_rand($agencies)] : null;
                $userId   = !empty($userIds) ? $userIds[array_rand($userIds)] : null;

                if (!$userId && !$agencyId) continue;

                // Deal aléatoire (10%)
                $isDeal       = rand(1, 10) === 1;
                $originalPrice = $isDeal ? (int) ($price * (1 + rand(10, 30) / 100)) : null;

                $status = rand(1, 10) <= 8 ? 'active' : 'sold'; // 80% actif

                $desc = self::DESCRIPTIONS_FR[array_rand(self::DESCRIPTIONS_FR)];

                Vehicle::create([
                    'user_id'          => $userId,
                    'agency_id'        => $agencyId,
                    'brand_id'         => $brand->id,
                    'vehicle_model_id' => $model->id,
                    'year'             => $year,
                    'mileage_km'       => $mileage,
                    'price_mru'        => $price,
                    'price_negotiable' => rand(0, 1),
                    'currency'         => 'MRU',
                    'fuel'             => $fuel,
                    'transmission'     => $transmission,
                    'body_type'        => $bodyType,
                    'color'            => $color,
                    'condition'        => $condition,
                    'description_fr'   => $desc,
                    'description_ar'   => null,
                    'city_id'          => $cityId,
                    'status'           => $status,
                    'is_deal'          => $isDeal,
                    'original_price'   => $originalPrice,
                    'views_count'      => rand(0, 500),
                    'contacts_count'   => rand(0, 50),
                    'published_at'     => now()->subDays(rand(0, 180)),
                    'expires_at'       => now()->addDays(rand(30, 180)),
                ]);

                $created++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->command->newLine();
        $this->command->info("✓ {$created} véhicules créés.");

        // Recalcul des price ratings
        $this->command->info('Calcul des price ratings...');
        $this->call('vehicles:recalc-prices');
    }

    private function pickBrand(array $names): string
    {
        // Pondération : Toyota 20%, Hyundai 12%, Nissan 12%, reste distribué
        $weights = [
            'Toyota' => 20, 'Hyundai' => 12, 'Nissan' => 12, 'Kia' => 8,
            'Mitsubishi' => 7, 'Renault' => 5, 'Peugeot' => 4, 'Mercedes-Benz' => 4,
            'Ford' => 4, 'Volkswagen' => 3, 'BMW' => 3, 'Dacia' => 3,
            'Suzuki' => 2, 'Honda' => 2, 'Chery' => 2, 'Geely' => 2,
            'BYD' => 2, 'Mazda' => 1, 'Isuzu' => 1, 'Land Rover' => 1,
            'Lexus' => 1, 'Audi' => 1, 'Fiat' => 1, 'Citroën' => 1, 'Opel' => 1,
        ];

        $available = array_intersect_key($weights, array_flip($names));
        if (empty($available)) return $names[array_rand($names)];

        $total = array_sum($available);
        $rand  = rand(1, $total);
        $cumul = 0;
        foreach ($available as $name => $w) {
            $cumul += $w;
            if ($rand <= $cumul) return $name;
        }
        return array_key_first($available);
    }

    private function pickWeighted(array $items, array $weights): string
    {
        $total = array_sum($weights);
        $rand  = rand(1, $total);
        $cumul = 0;
        foreach ($items as $i => $item) {
            $cumul += $weights[$i];
            if ($rand <= $cumul) return $item;
        }
        return $items[0];
    }

    private function pickBodyType(string $brand, string $model): string
    {
        // Mapping modèles connus
        $map = [
            'Hilux' => 'pickup', 'Navara' => 'pickup', 'Ranger' => 'pickup',
            'L200' => 'pickup', 'D-Max' => 'pickup', 'BT-50' => 'pickup',
            'Land Cruiser' => 'suv', 'Prado' => 'suv', 'Patrol' => 'suv',
            'Pajero' => 'suv', 'RAV4' => 'suv', 'Tucson' => 'suv',
            'Santa Fe' => 'suv', 'Sportage' => 'suv', 'Sorento' => 'suv',
            'Qashqai' => 'suv', 'X-Trail' => 'suv', 'Outlander' => 'suv',
            'Duster' => 'suv', 'Tiggo 4' => 'suv', 'Tiggo 7' => 'suv',
            'Tiggo 8' => 'suv', 'Coolray' => 'suv', 'Atlas' => 'suv',
            'CX-5' => 'suv', 'CX-3' => 'suv', 'Vitara' => 'suv',
            'CR-V' => 'suv', 'HR-V' => 'suv', 'GLC' => 'suv',
            'GLE' => 'suv', 'GLS' => 'suv', 'X3' => 'suv', 'X5' => 'suv',
            'Q5' => 'suv', 'Q7' => 'suv', 'Discovery' => 'suv',
            'Defender' => 'suv', 'Range Rover' => 'suv',
            'Hiace' => 'van', 'Sprinter' => 'van', 'Trafic' => 'van',
            'Kangoo' => 'van', 'Partner' => 'van', 'Berlingo' => 'van',
            'Boxer' => 'van', 'Transporter' => 'van', 'H1' => 'van',
            'Carnival' => 'minivan',
            'Corolla' => 'sedan', 'Camry' => 'sedan', 'Yaris' => 'hatchback',
            'Elantra' => 'sedan', 'Accent' => 'sedan', 'i10' => 'hatchback',
            'i20' => 'hatchback', 'Clio' => 'hatchback', 'Polo' => 'hatchback',
            'Golf' => 'hatchback', 'Swift' => 'hatchback', 'Civic' => 'sedan',
        ];

        return $map[$model] ?? self::BODY_TYPES[array_rand(self::BODY_TYPES)];
    }

    private function roundPrice(int $price): int
    {
        // Arrondit au 50 000 MRU le plus proche
        return (int) (round($price / 50_000) * 50_000);
    }
}
