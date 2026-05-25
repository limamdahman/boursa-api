<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\PriceRating;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Service d'évaluation du prix d'un véhicule par rapport au marché.
 * Calibré pour le marché mauritanien (petit catalogue, fallbacks progressifs).
 */
final class PriceRatingService
{
    private const MIN_COMPARABLES = 3;
    private const YEAR_RANGE = 3;
    private const YEAR_RANGE_WIDE = 5;
    private const MILEAGE_TOLERANCE = 0.20;

    private const VERY_GOOD_THRESHOLD = 0.70;
    private const GOOD_THRESHOLD = 0.85;
    private const FAIR_HIGH_THRESHOLD = 1.15;
    private const HIGH_THRESHOLD = 1.30;

    /**
     * @return array{rating: ?PriceRating, score: ?float, benchmark: ?array}
     */
    public function rate(Vehicle $vehicle): array
    {
        $result = $this->computeRating($vehicle);

        $vehicle->forceFill([
            'price_rating' => $result['rating']?->value,
            'price_score' => $result['score'],
            'price_benchmark_data' => $result['benchmark'],
            'price_rating_computed_at' => now(),
        ])->saveQuietly();

        return $result;
    }

    /**
     * @return array{rating: ?PriceRating, score: ?float, benchmark: ?array}
     */
    public function computeRating(Vehicle $vehicle): array
    {
        if (! $vehicle->price_mru || $vehicle->price_mru <= 0) {
            return ['rating' => null, 'score' => null, 'benchmark' => null];
        }

        [$comparables, $confidence, $criteria] = $this->findComparablesWithFallback($vehicle);
        $count = count($comparables);

        if ($count < self::MIN_COMPARABLES) {
            return [
                'rating' => null,
                'score' => null,
                'benchmark' => [
                    'count' => $count,
                    'reason' => 'insufficient_comparables',
                    'min_required' => self::MIN_COMPARABLES,
                ],
            ];
        }

        sort($comparables);
        $median = $this->median($comparables);
        $price = (float) $vehicle->price_mru;
        $ratio = $price / $median;
        $rating = $this->scoreToRating($ratio);

        return [
            'rating' => $rating,
            'score' => round($ratio, 4),
            'benchmark' => [
                'count' => $count,
                'confidence' => $confidence,
                'median' => (int) $median,
                'min' => (int) $comparables[0],
                'max' => (int) $comparables[$count - 1],
                'p25' => (int) $this->percentile($comparables, 0.25),
                'p75' => (int) $this->percentile($comparables, 0.75),
                'criteria' => $criteria,
            ],
        ];
    }

    /**
     * Recherche progressive en 4 niveaux jusqu'à trouver MIN_COMPARABLES.
     *
     * @return array{0: array<int, float>, 1: string, 2: array}
     */
    private function findComparablesWithFallback(Vehicle $vehicle): array
    {
        // Niveau 1 : brand+model+year±3+km±20%+fuel+transmission
        $prices = $this->queryComparables($vehicle, [
            'same_model' => true,
            'year_range' => self::YEAR_RANGE,
            'with_mileage' => true,
            'with_fuel' => true,
            'with_transmission' => true,
        ]);
        if (count($prices) >= self::MIN_COMPARABLES) {
            return [$prices, 'high', [
                'level' => 1,
                'description' => 'Même modèle, année ±3, km ±20%, carburant et transmission identiques',
            ]];
        }

        // Niveau 2 : brand+model+year±3+fuel (sans km ni transmission)
        $prices = $this->queryComparables($vehicle, [
            'same_model' => true,
            'year_range' => self::YEAR_RANGE,
            'with_fuel' => true,
        ]);
        if (count($prices) >= self::MIN_COMPARABLES) {
            return [$prices, 'high', [
                'level' => 2,
                'description' => 'Même modèle, année ±3, carburant identique',
            ]];
        }

        // Niveau 3 : brand+model+year±5 (sans rien d'autre)
        $prices = $this->queryComparables($vehicle, [
            'same_model' => true,
            'year_range' => self::YEAR_RANGE_WIDE,
        ]);
        if (count($prices) >= self::MIN_COMPARABLES) {
            return [$prices, 'medium', [
                'level' => 3,
                'description' => 'Même modèle, année ±5',
            ]];
        }

        // Niveau 4 : brand seule+year±5+fuel (sans model)
        $prices = $this->queryComparables($vehicle, [
            'year_range' => self::YEAR_RANGE_WIDE,
            'with_fuel' => true,
        ]);
        if (count($prices) >= self::MIN_COMPARABLES) {
            return [$prices, 'low', [
                'level' => 4,
                'description' => 'Même marque, année ±5, carburant identique',
            ]];
        }

        // Niveau 5 : brand seule+year±5 (très large)
        $prices = $this->queryComparables($vehicle, [
            'year_range' => self::YEAR_RANGE_WIDE,
        ]);
        if (count($prices) >= self::MIN_COMPARABLES) {
            return [$prices, 'low', [
                'level' => 5,
                'description' => 'Même marque, année ±5',
            ]];
        }

        return [[], 'none', ['level' => 0]];
    }

    /**
     * @param array<string, bool|int> $opts
     * @return array<int, float>
     */
    private function queryComparables(Vehicle $vehicle, array $opts): array
    {
        $yearRange = (int) ($opts['year_range'] ?? self::YEAR_RANGE);

        $query = DB::table('vehicles')
            ->where('id', '!=', $vehicle->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where('brand_id', $vehicle->brand_id)
            ->whereBetween('year', [$vehicle->year - $yearRange, $vehicle->year + $yearRange])
            ->where('price_mru', '>', 0);

        if (! empty($opts['same_model'])) {
            $query->where('vehicle_model_id', $vehicle->vehicle_model_id);
        }

        if (! empty($opts['with_mileage']) && $vehicle->mileage_km && $vehicle->mileage_km > 0) {
            $kmMin = (int) ($vehicle->mileage_km * (1 - self::MILEAGE_TOLERANCE));
            $kmMax = (int) ($vehicle->mileage_km * (1 + self::MILEAGE_TOLERANCE));
            $query->whereBetween('mileage_km', [$kmMin, $kmMax]);
        }

        if (! empty($opts['with_fuel']) && $vehicle->fuel) {
            $query->where('fuel', $vehicle->fuel);
        }

        if (! empty($opts['with_transmission']) && $vehicle->transmission) {
            $query->where('transmission', $vehicle->transmission);
        }

        return $query->pluck('price_mru')->map(fn ($p) => (float) $p)->toArray();
    }

    private function scoreToRating(float $ratio): PriceRating
    {
        return match (true) {
            $ratio < self::VERY_GOOD_THRESHOLD => PriceRating::VERY_GOOD,
            $ratio < self::GOOD_THRESHOLD => PriceRating::GOOD,
            $ratio < self::FAIR_HIGH_THRESHOLD => PriceRating::FAIR,
            $ratio < self::HIGH_THRESHOLD => PriceRating::HIGH,
            default => PriceRating::VERY_HIGH,
        };
    }

    /**
     * @param array<int, float> $sorted
     */
    private function median(array $sorted): float
    {
        $count = count($sorted);
        if ($count === 0) {
            return 0.0;
        }
        $mid = (int) ($count / 2);

        return $count % 2 === 0
            ? ($sorted[$mid - 1] + $sorted[$mid]) / 2.0
            : $sorted[$mid];
    }

    /**
     * @param array<int, float> $sorted
     */
    private function percentile(array $sorted, float $p): float
    {
        $count = count($sorted);
        if ($count === 0) {
            return 0.0;
        }
        $idx = (int) floor($p * ($count - 1));

        return $sorted[$idx];
    }
}
