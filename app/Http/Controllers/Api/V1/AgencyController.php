<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AgencyDetailResource;
use App\Http\Resources\V1\AgencyListResource;
use App\Models\Agency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AgencyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 12);
        $perPage = max(5, min(50, $perPage));
        $hasFilters = $request->filled('city_id') || $request->filled('verified');

        // Cache uniquement requêtes sans filtres
        if (!$hasFilters && $perPage === 12) {
            $paginated = \Illuminate\Support\Facades\Cache::remember('agencies.list.default', 300, function () {
                return Agency::query()
                    ->with('city:id,name_fr,name_ar')
                    ->withCount(['vehicles' => fn ($q) => $q->where('status', 'active')])
                    ->orderByRaw('CASE WHEN verified_at IS NOT NULL THEN 0 ELSE 1 END')
                    ->orderByDesc('vehicles_count')
                    ->orderBy('name')
                    ->paginate(12);
            });
            return AgencyListResource::collection($paginated);
        }

        $query = Agency::query()
            ->with('city:id,name_fr,name_ar')
            ->withCount(['vehicles' => fn ($q) => $q->where('status', 'active')]);

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }
        if ($request->filled('verified') && $request->boolean('verified')) {
            $query->whereNotNull('verified_at');
        }
        $query->orderByRaw('CASE WHEN verified_at IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('vehicles_count')
            ->orderBy('name');

        return AgencyListResource::collection($query->paginate($perPage));
    }

    public function show(string $slug): AgencyDetailResource|JsonResponse
    {
        $agency = Agency::query()
            ->with('city:id,name_fr,name_ar')
            ->where('slug', $slug)
            ->first();

        if (! $agency) {
            return response()->json(['message' => 'Agence introuvable.'], 404);
        }

        return new AgencyDetailResource($agency);
    }
}
