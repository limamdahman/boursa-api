<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agency;

use App\Actions\Vehicles\CreateVehicleAction;
use App\Actions\Vehicles\PublishVehicleAction;
use App\Actions\Vehicles\UpdateVehicleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\StoreVehicleRequest;
use App\Http\Requests\Vehicles\UpdateVehicleRequest;
use App\Http\Resources\Agency\AgencyVehicleResource;
use App\Models\Vehicle;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AgencyVehicleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $agency = $request->user()->agency;

        if (! $agency) {
            return response()->json(['message' => 'Aucune agence.'], 404);
        }

        $query = $agency->vehicles()
            ->with(['brand', 'vehicleModel', 'media' => fn ($q) => $q->orderBy('sort_order')])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return AgencyVehicleResource::collection($query->paginate(20));
    }

    public function show(Request $request, string $id): AgencyVehicleResource|JsonResponse
    {
        $vehicle = Vehicle::with(['brand', 'vehicleModel', 'city', 'media' => fn ($q) => $q->orderBy('sort_order')])
            ->find($id);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        if (! $request->user()->can('view', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        return new AgencyVehicleResource($vehicle);
    }

    public function store(StoreVehicleRequest $request, CreateVehicleAction $action): AgencyVehicleResource|JsonResponse
    {
        $agency = $request->user()->agency;

        if (! $agency) {
            return response()->json(['message' => 'Aucune agence.'], 404);
        }

        $count = $agency->vehicles()->whereIn('status', ['active', 'pending'])->count();
        if ($count >= $agency->quota_active_listings) {
            return response()->json([
                'message' => sprintf('Quota atteint (%d annonces actives max).', $agency->quota_active_listings),
            ], 403);
        }

        $vehicle = $action->execute($agency, $request->validated());

        return new AgencyVehicleResource($vehicle);
    }

    public function update(UpdateVehicleRequest $request, string $id, UpdateVehicleAction $action): AgencyVehicleResource|JsonResponse
    {
        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        if (! $request->user()->can('update', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $vehicle = $action->execute($vehicle, $request->validated());

        return new AgencyVehicleResource($vehicle);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        if (! $request->user()->can('delete', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Véhicule supprimé.']);
    }

    public function publish(Request $request, string $id, PublishVehicleAction $action): AgencyVehicleResource|JsonResponse
    {
        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        if (! $request->user()->can('publish', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        try {
            $vehicle = $action->execute($vehicle);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new AgencyVehicleResource($vehicle->load(['brand', 'vehicleModel', 'media']));
    }
}
