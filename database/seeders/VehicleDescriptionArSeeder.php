<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleDescriptionArSeeder extends Seeder
{
    /**
     * Génère des descriptions en arabe pour les véhicules qui n'en ont pas.
     * Basé sur la structure des descriptions FR existantes.
     */
    public function run(): void
    {
        // Translittération des marques en arabe
        $brandsAr = [
            'Toyota' => 'تويوتا',
            'Hyundai' => 'هيونداي',
            'Nissan' => 'نيسان',
            'Kia' => 'كيا',
            'Mercedes-Benz' => 'مرسيدس',
            'Mitsubishi' => 'ميتسوبيشي',
            'Renault' => 'رينو',
            'Peugeot' => 'بيجو',
            'Volkswagen' => 'فولكسفاجن',
            'Ford' => 'فورد',
            'BMW' => 'بي إم دبليو',
            'Audi' => 'أودي',
            'Land Rover' => 'لاند روفر',
            'Lexus' => 'لكزس',
            'Isuzu' => 'إيسوزو',
            'Mazda' => 'مازدا',
            'Suzuki' => 'سوزوكي',
            'Honda' => 'هوندا',
            'Chery' => 'شيري',
            'Geely' => 'جيلي',
            'BYD' => 'بي واي دي',
            'Fiat' => 'فيات',
            'Citroën' => 'سيتروين',
            'Opel' => 'أوبل',
            'Dacia' => 'داشيا',
        ];

        $fuelAr = [
            'gasoline' => 'بنزين',
            'diesel' => 'ديزل',
            'hybrid' => 'هايبرد',
            'electric' => 'كهربائي',
            'lpg' => 'غاز',
            'gpl' => 'غاز',
        ];

        $transmissionAr = [
            'manual' => 'يدوي',
            'automatic' => 'أوتوماتيكي',
        ];

        // 5 variantes de phrases pour éviter la répétition
        $templates = [
            "{brand} {model} موديل {year} بحالة ممتازة. ناقل {transmission}، {fuel}. مالك أول، صيانة منتظمة. الأوراق سليمة.",
            "{brand} {model} {year} بحالة جيدة جداً. ناقل حركة {transmission}، تعمل بـ{fuel}. صيانة دورية مكتملة، جميع الوثائق متوفرة.",
            "{brand} {model} موديل {year} للبيع. ناقل {transmission}، محرك {fuel}. مالك واحد فقط، صيانة منتظمة لدى الوكالة.",
            "للبيع {brand} {model} {year} بحالة ممتازة. علبة سرعات {transmission}، {fuel}. السيارة مصانة وأوراقها كاملة وسارية.",
            "{brand} {model} {year}، حالة ممتازة. ناقل {transmission}، نوع الوقود {fuel}. الصيانة منتظمة والأوراق جاهزة للتحويل.",
        ];

        $vehicles = Vehicle::whereNull('description_ar')
            ->whereNotNull('description_fr')
            ->with(['brand', 'vehicleModel'])
            ->get();

        $count = 0;
        $skipped = 0;

        foreach ($vehicles as $vehicle) {
            $brandName = $vehicle->brand?->name;
            $modelName = $vehicle->vehicleModel?->name;
            $year = $vehicle->year;
            $fuel = $vehicle->fuel;
            $transmission = $vehicle->transmission;

            if (! $brandName || ! $modelName || ! $year) {
                $skipped++;
                continue;
            }

            $brandAr = $brandsAr[$brandName] ?? $brandName;
            $fuelArVal = $fuelAr[$fuel] ?? 'بنزين';
            $transmissionArVal = $transmissionAr[$transmission] ?? 'أوتوماتيكي';

            // Pick template basé sur l'id du véhicule (déterministe, pas random)
            $templateIndex = crc32((string) $vehicle->id) % count($templates);
            $template = $templates[$templateIndex];

            $description = str_replace(
                ['{brand}', '{model}', '{year}', '{fuel}', '{transmission}'],
                [$brandAr, $modelName, $year, $fuelArVal, $transmissionArVal],
                $template
            );

            $vehicle->update(['description_ar' => $description]);
            $count++;
        }

        $this->command->info("✓ {$count} descriptions AR générées, {$skipped} ignorées (data incomplète)");
    }
}
