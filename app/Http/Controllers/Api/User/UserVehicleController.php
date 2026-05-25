<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\User;

use App\Actions\Vehicles\CreateUserVehicleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\StoreVehicleRequest;
use App\Http\Requests\Vehicles\UpdateVehicleRequest;
use App\Http\Requests\Vehicles\UploadVehicleMediaRequest;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use App\Services\Media\MediaUploadService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserVehicleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $vehicles = Vehicle::query()
            ->where('user_id', $user->id)
            ->whereNull('agency_id')
            ->with(['brand', 'vehicleModel', 'city', 'media' => fn ($q) => $q->orderBy('sort_order')])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($vehicles);
    }

    public function store(StoreVehicleRequest $request, CreateUserVehicleAction $action): JsonResponse
    {
        $user = $request->user();

        // Limite simple : max 5 annonces actives + pending par particulier
        $count = Vehicle::query()
            ->where('user_id', $user->id)
            ->whereNull('agency_id')
            ->whereIn('status', ['active', 'pending'])
            ->count();

        if ($count >= 5) {
            return response()->json([
                'message' => 'Quota atteint (5 annonces max par particulier).',
            ], 403);
        }

        $vehicle = $action->execute($user, $request->validated());

        return response()->json([
            'message' => 'Annonce créée, en attente de modération.',
            'data' => [
                'id' => $vehicle->id,
                'status' => $vehicle->status->value,
                'brand' => $vehicle->brand?->name,
                'model' => $vehicle->vehicleModel?->name,
                'year' => $vehicle->year,
            ],
        ], 201);
    }

    public function uploadMedia(UploadVehicleMediaRequest $request, string $vehicleId, MediaUploadService $service): JsonResponse
    {
        $vehicle = Vehicle::find($vehicleId);
        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }
        if (! $request->user()->can('manageMedia', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        try {
            $media = $service->store(
                $vehicle,
                $request->file('photo'),
                (bool) $request->boolean('is_cover')
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Photo uploadée. Traitement en cours.',
            'media' => [
                'id' => $media->id,
                'url_original' => $media->url_original,
                'is_cover' => $media->is_cover,
                'sort_order' => $media->sort_order,
            ],
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::with(['brand', 'vehicleModel', 'city', 'media'])->find($id);
        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }
        if (! $request->user()->can('view', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        return response()->json(['data' => $vehicle]);
    }

    public function update(UpdateVehicleRequest $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::find($id);
        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }
        if (! $request->user()->can('update', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $data = $request->validated();
        $lat = $data['latitude'] ?? null;
        $lng = $data['longitude'] ?? null;
        unset($data['latitude'], $data['longitude']);

        // Si l'annonce était refusée/rejetée, retour en pending après édition
        if ($vehicle->status?->value === 'rejected') {
            $data['status'] = 'pending';
            $data['moderation_notes'] = null;
        }

        $vehicle->update($data);

        if ($lat !== null && $lng !== null) {
            DB::statement(
                'UPDATE vehicles SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                [(float) $lng, (float) $lat, $vehicle->id]
            );
        }

        return response()->json([
            'message' => 'Annonce mise à jour.',
            'data' => $vehicle->fresh()->load(['brand', 'vehicleModel', 'city']),
        ]);
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
        return response()->json(['message' => 'Annonce supprimée.']);
    }

    public function destroyMedia(Request $request, string $vehicleId, string $mediaId, MediaUploadService $service): JsonResponse
    {
        $vehicle = Vehicle::find($vehicleId);
        $media = VehicleMedia::where('id', $mediaId)->where('vehicle_id', $vehicleId)->first();
        if (! $vehicle || ! $media) {
            return response()->json(['message' => 'Ressource introuvable.'], 404);
        }
        if (! $request->user()->can('manageMedia', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        $wasCover = $media->is_cover;
        $service->delete($media);
        if ($wasCover) {
            $next = $vehicle->media()->orderBy('sort_order')->first();
            if ($next) {
                $next->update(['is_cover' => true]);
            }
        }
        return response()->json(['message' => 'Photo supprimée.']);
    }

    public function setCoverMedia(Request $request, string $vehicleId, string $mediaId): JsonResponse
    {
        $vehicle = Vehicle::find($vehicleId);
        $media = VehicleMedia::where('id', $mediaId)->where('vehicle_id', $vehicleId)->first();
        if (! $vehicle || ! $media) {
            return response()->json(['message' => 'Ressource introuvable.'], 404);
        }
        if (! $request->user()->can('manageMedia', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }
        DB::transaction(function () use ($vehicle, $media): void {
            $vehicle->media()->update(['is_cover' => false]);
            $media->update(['is_cover' => true]);
        });
        return response()->json(['message' => 'Photo de couverture mise à jour.']);
    }
}
