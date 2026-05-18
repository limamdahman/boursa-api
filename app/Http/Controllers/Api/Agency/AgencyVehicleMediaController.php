<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\ReorderVehicleMediaRequest;
use App\Http\Requests\Vehicles\UploadVehicleMediaRequest;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use App\Services\Media\MediaUploadService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgencyVehicleMediaController extends Controller
{
    public function store(UploadVehicleMediaRequest $request, string $vehicleId, MediaUploadService $service): JsonResponse
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
            'message' => 'Photo uploadée. Traitement asynchrone en cours.',
            'media' => [
                'id' => $media->id,
                'url_original' => $media->url_original,
                'is_cover' => $media->is_cover,
                'watermarked' => $media->watermarked,
                'sort_order' => $media->sort_order,
            ],
        ], 201);
    }

    public function destroy(Request $request, string $vehicleId, string $mediaId, MediaUploadService $service): JsonResponse
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

    public function reorder(ReorderVehicleMediaRequest $request, string $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::find($vehicleId);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        if (! $request->user()->can('manageMedia', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $order = $request->validated()['order'];

        DB::transaction(function () use ($vehicle, $order): void {
            foreach ($order as $idx => $mediaId) {
                $vehicle->media()->where('id', $mediaId)->update(['sort_order' => $idx]);
            }
        });

        return response()->json(['message' => 'Ordre mis à jour.']);
    }
}
