<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\ListVehiclesRequest;
use App\Http\Resources\V1\VehicleListResource;
use App\Http\Resources\V1\VehicleResource;
use App\Models\Vehicle;
use App\Services\Search\SearchQueryParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehicleController extends Controller
{
    public function index(ListVehiclesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        // Parser q et merger avec les filtres explicites
        $fulltext = null;
        $parsedFilters = [];
        $parsedChips = [];
        if (! empty($filters['q'])) {
            $parser = app(SearchQueryParser::class);
            $parsed = $parser->parse($filters['q'], app()->getLocale());
            $fulltext = $parsed['fulltext'] ?? null;
            $parsedFilters = $parsed['filters'] ?? [];
            $parsedChips = $parsed['parsed_chips'] ?? [];
            foreach ($parsedFilters as $k => $v) {
                if (empty($filters[$k])) $filters[$k] = $v;
            }
        }

        $query = Vehicle::query()
            ->with(['brand:id,name,slug', 'vehicleModel:id,name', 'city:id,name_fr,name_ar', 'coverMedia'])
            ->where('status', VehicleStatus::ACTIVE->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        foreach (['brand_id', 'vehicle_model_id', 'city_id', 'agency_id', 'fuel', 'transmission', 'body_type', 'condition'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['year_min'])) {
            $query->where('year', '>=', $filters['year_min']);
        }
        if (isset($filters['year_max'])) {
            $query->where('year', '<=', $filters['year_max']);
        }
        if (isset($filters['price_min'])) {
            $query->where('price_mru', '>=', $filters['price_min']);
        }
        if (isset($filters['price_max'])) {
            $query->where('price_mru', '<=', $filters['price_max']);
        }
        if (isset($filters['mileage_max'])) {
            $query->where('mileage_km', '<=', $filters['mileage_max']);
        }

        if (! empty($filters['exclude'])) {
            $query->where('id', '!=', $filters['exclude']);
        }

        $hasGeo = isset($filters['lat'], $filters['lng'], $filters['radius_km']);
        if ($hasGeo) {
            $query->nearby(
                (float) $filters['lat'],
                (float) $filters['lng'],
                ((int) $filters['radius_km']) * 1000
            );
        }

        $sort = $filters['sort'] ?? ($hasGeo ? 'distance' : 'recent');
        if (! $hasGeo) {
            match ($sort) {
                'price_asc' => $query->orderBy('price_mru'),
                'price_desc' => $query->orderByDesc('price_mru'),
                'year_desc' => $query->orderByDesc('year'),
                'mileage_asc' => $query->orderBy('mileage_km'),
                default => $query->orderByDesc('published_at'),
            };
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        $collection = VehicleListResource::collection($query->paginate($perPage));

        // Inclure les infos de parsing dans meta (lecture frontend pour chips + sidebar)
        if (! empty($parsedFilters) || ! empty($parsedChips)) {
            $collection->additional([
                'meta' => [
                    'parsed_filters' => $parsedFilters,
                    'parsed_chips' => $parsedChips,
                ],
            ]);
        }

        return $collection;
    }

    public function show(string $id): VehicleResource|JsonResponse
    {
        $vehicle = Vehicle::query()
            ->with([
                'brand',
                'vehicleModel',
                'city',
                'user', 'agency',
                'agency.city',
                'media',
            ])
            ->where('status', VehicleStatus::ACTIVE->value)
            ->whereNotNull('published_at')
            ->where('id', $id)
            ->first();

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        Vehicle::where('id', $vehicle->id)->update([
            'views_count' => $vehicle->views_count + 1,
        ]);

        return new VehicleResource($vehicle);
    }

    public function similar(string $id): AnonymousResourceCollection|JsonResponse
    {
        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        $minPrice = (int) ($vehicle->price_mru * 0.7);
        $maxPrice = (int) ($vehicle->price_mru * 1.3);

        // Stratégie progressive : même brand → même body_type → fallback active
        $baseQuery = fn () => Vehicle::query()
            ->with(['brand:id,name,slug', 'vehicleModel:id,name', 'city:id,name_fr', 'coverMedia'])
            ->where('status', VehicleStatus::ACTIVE->value)
            ->where('id', '!=', $vehicle->id);

        // 1. Même brand + prix proche
        $similar = $baseQuery()
            ->where('brand_id', $vehicle->brand_id)
            ->whereBetween('price_mru', [$minPrice, $maxPrice])
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        // 2. Si insuffisant, même body_type + prix proche
        if ($similar->count() < 4 && $vehicle->body_type) {
            $needed = 6 - $similar->count();
            $excluded = $similar->pluck('id')->push($vehicle->id);
            $more = $baseQuery()
                ->whereNotIn('id', $excluded)
                ->where('body_type', $vehicle->body_type)
                ->whereBetween('price_mru', [$minPrice, $maxPrice])
                ->orderByDesc('published_at')
                ->limit($needed)
                ->get();
            $similar = $similar->concat($more);
        }

        // 3. Fallback : n'importe quel actif récent dans la même tranche de prix
        if ($similar->count() < 4) {
            $needed = 6 - $similar->count();
            $excluded = $similar->pluck('id')->push($vehicle->id);
            $more = $baseQuery()
                ->whereNotIn('id', $excluded)
                ->whereBetween('price_mru', [$minPrice * 0.5, $maxPrice * 1.5])
                ->orderByDesc('published_at')
                ->limit($needed)
                ->get();
            $similar = $similar->concat($more);
        }

        // 4. Dernier fallback : n'importe quel actif récent
        if ($similar->count() < 4) {
            $needed = 6 - $similar->count();
            $excluded = $similar->pluck('id')->push($vehicle->id);
            $more = $baseQuery()
                ->whereNotIn('id', $excluded)
                ->orderByDesc('published_at')
                ->limit($needed)
                ->get();
            $similar = $similar->concat($more);
        }

        return VehicleListResource::collection($similar);
    }
}
