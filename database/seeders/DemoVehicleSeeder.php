<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class DemoVehicleSeeder extends Seeder
{
    public function run(): void
    {
        $agencies = Agency::factory()->count(6)->create();

        $totalVehicles = 0;
        foreach ($agencies as $agency) {
            $count = random_int(5, 9);
            Vehicle::factory()->count($count)->create([
                'agency_id' => $agency->id,
                'user_id' => $agency->user_id,
            ]);
            $totalVehicles += $count;
        }

        $this->command->info(sprintf(
            '  Created %d agencies and %d vehicles',
            $agencies->count(),
            $totalVehicles
        ));
    }
}
