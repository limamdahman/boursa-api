<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\Pricing\PriceRatingService;
use Illuminate\Console\Command;

final class RecalcPriceRatingsCommand extends Command
{
    protected $signature = 'vehicles:recalc-price-ratings 
                            {--only-active : Limiter aux véhicules actifs}
                            {--vehicle= : ID d\'un véhicule spécifique}';

    protected $description = 'Recalcule les Preisbewertung (price_rating) pour tous les véhicules';

    public function handle(PriceRatingService $service): int
    {
        $query = Vehicle::query();

        if ($this->option('only-active')) {
            $query->where('status', 'active');
        }

        if ($vehicleId = $this->option('vehicle')) {
            $query->where('id', $vehicleId);
        }

        $total = $query->count();
        $this->info("Recalcul de {$total} véhicules...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = ['rated' => 0, 'unrated' => 0, 'errors' => 0];
        $distribution = [];

        $query->chunkById(50, function ($vehicles) use ($service, $bar, &$stats, &$distribution) {
            foreach ($vehicles as $vehicle) {
                try {
                    $result = $service->rate($vehicle);
                    if ($result['rating']) {
                        $stats['rated']++;
                        $key = $result['rating']->value;
                        $distribution[$key] = ($distribution[$key] ?? 0) + 1;
                    } else {
                        $stats['unrated']++;
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->newLine();
                    $this->error("Erreur véhicule {$vehicle->id}: " . $e->getMessage());
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Terminé : {$stats['rated']} évalués, {$stats['unrated']} non évalués, {$stats['errors']} erreurs");

        if (count($distribution) > 0) {
            $this->newLine();
            $this->info('Distribution :');
            foreach ($distribution as $rating => $count) {
                $this->line("  {$rating}: {$count}");
            }
        }

        return self::SUCCESS;
    }
}
