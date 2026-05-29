#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Mapping marque → slug imagin.studio
const MAKE_MAP = [
    'Toyota'        => 'toyota',
    'Hyundai'       => 'hyundai',
    'Nissan'        => 'nissan',
    'Kia'           => 'kia',
    'Mercedes-Benz' => 'mercedes-benz',
    'Mitsubishi'    => 'mitsubishi',
    'Renault'       => 'renault',
    'Peugeot'       => 'peugeot',
    'Volkswagen'    => 'volkswagen',
    'Ford'          => 'ford',
    'BMW'           => 'bmw',
    'Audi'          => 'audi',
    'Land Rover'    => 'land-rover',
    'Lexus'         => 'lexus',
    'Isuzu'         => 'isuzu',
    'Mazda'         => 'mazda',
    'Suzuki'        => 'suzuki',
    'Honda'         => 'honda',
    'Chery'         => 'chery',
    'Geely'         => 'geely',
    'BYD'           => 'byd',
    'Fiat'          => 'fiat',
    'Citroën'       => 'citroen',
    'Opel'          => 'opel',
    'Dacia'         => 'dacia',
];

// Mapping modèle → modelFamily imagin.studio
const MODEL_MAP = [
    // Toyota
    'Hilux'         => 'hilux',
    'Land Cruiser'  => 'land-cruiser',
    'Prado'         => 'land-cruiser-prado',
    'RAV4'          => 'rav4',
    'Corolla'       => 'corolla',
    'Camry'         => 'camry',
    'Yaris'         => 'yaris',
    'Hiace'         => 'hiace',
    'Fortuner'      => 'fortuner',
    // Hyundai
    'Tucson'        => 'tucson',
    'Santa Fe'      => 'santa-fe',
    'Elantra'       => 'elantra',
    'Accent'        => 'accent',
    'i10'           => 'i10',
    'i20'           => 'i20',
    'i30'           => 'i30',
    'Sonata'        => 'sonata',
    'Creta'         => 'creta',
    // Nissan
    'Patrol'        => 'patrol',
    'Pathfinder'    => 'pathfinder',
    'Qashqai'       => 'qashqai',
    'X-Trail'       => 'x-trail',
    'Navara'        => 'navara',
    'Juke'          => 'juke',
    'Sentra'        => 'sentra',
    'Sunny'         => 'sunny',
    // Kia
    'Sportage'      => 'sportage',
    'Sorento'       => 'sorento',
    'Picanto'       => 'picanto',
    'Rio'           => 'rio',
    'Cerato'        => 'cerato',
    // Mercedes
    'Classe C'      => 'c-class',
    'Classe E'      => 'e-class',
    'Classe S'      => 's-class',
    'GLC'           => 'glc',
    'GLE'           => 'gle',
    'GLS'           => 'gls',
    'Sprinter'      => 'sprinter',
    // Mitsubishi
    'Pajero'        => 'pajero',
    'L200'          => 'l200',
    'Outlander'     => 'outlander',
    'ASX'           => 'asx',
    // Renault
    'Clio'          => 'clio',
    'Megane'        => 'megane',
    'Logan'         => 'logan',
    'Sandero'       => 'sandero',
    'Duster'        => 'duster',
    'Captur'        => 'captur',
    'Kangoo'        => 'kangoo',
    // Peugeot
    '208'           => '208',
    '308'           => '308',
    '2008'          => '2008',
    '3008'          => '3008',
    '5008'          => '5008',
    // Volkswagen
    'Golf'          => 'golf',
    'Polo'          => 'polo',
    'Passat'        => 'passat',
    'Tiguan'        => 'tiguan',
    'Touareg'       => 'touareg',
    // Ford
    'Ranger'        => 'ranger',
    'Focus'         => 'focus',
    'Fiesta'        => 'fiesta',
    'Kuga'          => 'kuga',
    'Explorer'      => 'explorer',
    // BMW
    'Série 3'       => '3-series',
    'Série 5'       => '5-series',
    'X3'            => 'x3',
    'X5'            => 'x5',
    'X6'            => 'x6',
    // Audi
    'A3'            => 'a3',
    'A4'            => 'a4',
    'A6'            => 'a6',
    'Q3'            => 'q3',
    'Q5'            => 'q5',
    'Q7'            => 'q7',
    // Land Rover
    'Range Rover'        => 'range-rover',
    'Range Rover Sport'  => 'range-rover-sport',
    'Discovery'          => 'discovery',
    'Defender'           => 'defender',
    // Lexus
    'RX'            => 'rx',
    'NX'            => 'nx',
    'LX'            => 'lx',
    // Mazda
    'CX-5'          => 'cx-5',
    'Mazda 3'       => '3',
    'Mazda 6'       => '6',
    // Suzuki
    'Vitara'        => 'vitara',
    'Jimny'         => 'jimny',
    'Swift'         => 'swift',
    // Honda
    'Civic'         => 'civic',
    'Accord'        => 'accord',
    'CR-V'          => 'cr-v',
    'HR-V'          => 'hr-v',
    // Dacia
    'Duster'        => 'duster',
    'Sandero'       => 'sandero',
    'Logan'         => 'logan',
];

// Couleurs imagin.studio
const COLORS = [
    'colour-white',
    'colour-black',
    'colour-silver',
    'colour-red',
    'colour-blue',
];

function buildImageUrl(string $make, string $model, string $color, int $angle = 29): string
{
    $makeSlug  = MAKE_MAP[$make]  ?? strtolower(str_replace([' ', '-'], '-', $make));
    $modelSlug = MODEL_MAP[$model] ?? strtolower(str_replace([' ', '/', '-'], '-', $model));

    return "https://cdn.imagin.studio/getimage?customer=test"
        . "&make={$makeSlug}"
        . "&modelFamily={$modelSlug}"
        . "&paintId={$color}"
        . "&angle={$angle}"
        . "&width=800";
}

echo "Suppression des anciennes photos...\n";
DB::table('vehicle_media')
    ->whereRaw("url_original LIKE '%unsplash%' OR url_original LIKE '%picsum%' OR url_original LIKE '%imagin%'")
    ->delete();

$vehicles = Vehicle::with(['brand', 'vehicleModel'])->get();
$total    = $vehicles->count();
echo "→ {$total} véhicules\n\n";

$inserts = [];
$bar     = 0;

// Angles de vue : face avant, 3/4 avant, intérieur simulé (même angle différente couleur)
$angles = [29, 13, 45];

foreach ($vehicles as $vehicle) {
    $make  = $vehicle->brand->name ?? 'Toyota';
    $model = $vehicle->vehicleModel->name ?? 'Corolla';

    // Couleur basée sur la couleur du véhicule
    $colorMap = [
        'Blanc'  => 'colour-white',
        'Noir'   => 'colour-black',
        'Gris'   => 'colour-silver',
        'Argent' => 'colour-silver',
        'Rouge'  => 'colour-red',
        'Bleu'   => 'colour-blue',
        'Beige'  => 'colour-white',
        'Or'     => 'colour-silver',
        'Marron' => 'colour-red',
        'Vert'   => 'colour-blue',
    ];
    $color = $colorMap[$vehicle->color ?? ''] ?? 'colour-white';

    for ($i = 0; $i < 3; $i++) {
        $url   = buildImageUrl($make, $model, $color, $angles[$i]);
        $thumb = buildImageUrl($make, $model, $color, $angles[$i]) . '&width=400';
        // width=400 pour le thumb
        $thumb = str_replace('&width=800', '&width=400', $url);

        $inserts[] = [
            'id'           => Str::uuid()->toString(),
            'vehicle_id'   => $vehicle->id,
            'url_original' => $url,
            'url_webp_lg'  => $url,
            'url_webp_md'  => $url,
            'url_thumb'    => $thumb,
            'sort_order'   => $i,
            'is_cover'     => $i === 0 ? 1 : 0,
            'watermarked'  => 0,
            'width'        => 800,
            'height'       => 534,
            'size_bytes'   => 180000,
            'created_at'   => now(),
            'updated_at'   => now(),
        ];
    }

    $bar++;

    if (count($inserts) >= 300) {
        DB::table('vehicle_media')->insert($inserts);
        $inserts = [];
        echo "\r→ {$bar}/{$total} véhicules...";
    }
}

if (!empty($inserts)) {
    DB::table('vehicle_media')->insert($inserts);
}

echo "\r→ {$bar}/{$total} véhicules traités   \n";
echo "\n✅ " . ($total * 3) . " photos insérées (imagin.studio par marque/modèle/couleur)\n";
