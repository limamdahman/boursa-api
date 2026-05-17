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
            DB::insert('
                INSERT INTO cities (name_fr, name_ar, slug, region, location, is_active, sort_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, true, ?, NOW(), NOW())
            ', [$nameFr, $nameAr, $slug, $region, $lng, $lat, $sortOrder]);
        }

        $this->command->info(sprintf('  Inserted %d cities', count($cities) + count($quarters)));
    }
}
