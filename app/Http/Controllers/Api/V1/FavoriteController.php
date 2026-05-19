<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gère les favoris des utilisateurs authentifiés.
 *
 * Endpoints :
 *  - GET    /api/v1/favorites           → liste des véhicules favoris (paginée)
 *  - GET    /api/v1/favorites/ids       → liste des UUIDs seulement (sync client)
 *  - POST   /api/v1/favorites/{id}      → ajoute un favori (idempotent)
 *  - DELETE /api/v1/favorites/{id}      → retire un favori (idempotent)
 */
class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        \assert($user instanceof User);

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(5, min(50, $perPage));

        $vehicles = Vehicle::query()
            ->where('status', 'active')
            ->whereIn('id', function ($query) use ($user) {
                $query->select('vehicle_id')
                    ->from('favorites')
                    ->where('user_id', $user->id);
            })
            ->with(['brand:id,name,slug', 'vehicleModel:id,name', 'city:id,name_fr,name_ar'])
            ->orderByDesc(
                DB::table('favorites')
                    ->select('created_at')
                    ->whereColumn('favorites.vehicle_id', 'vehicles.id')
                    ->where('favorites.user_id', $user->id)
            )
            ->paginate($perPage);

        // Format léger (comme listing public)
        $data = $vehicles->getCollection()->map(function (Vehicle $v) {
            $cover = $v->media()->orderBy('sort_order')->first();
            return [
                'id' => $v->id,
                'brand' => $v->brand ? [
                    'id' => $v->brand->id,
                    'name' => $v->brand->name,
                    'slug' => $v->brand->slug,
                ] : null,
                'model' => $v->vehicleModel ? [
                    'id' => $v->vehicleModel->id,
                    'name' => $v->vehicleModel->name,
                ] : null,
                'year' => $v->year,
                'price_mru' => $v->price_mru,
                'mileage_km' => $v->mileage_km,
                'fuel' => $v->fuel,
                'transmission' => $v->transmission,
                'body_type' => $v->body_type,
                'city' => $v->city ? [
                    'id' => $v->city->id,
                    'name_fr' => $v->city->name_fr,
                    'name_ar' => $v->city->name_ar,
                ] : null,
                'cover_image' => $cover?->url_webp_md ?? $cover?->url_original,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $vehicles->currentPage(),
                'last_page' => $vehicles->lastPage(),
                'per_page' => $vehicles->perPage(),
                'total' => $vehicles->total(),
            ],
        ]);
    }

    public function ids(Request $request): JsonResponse
    {
        $user = $request->user();
        \assert($user instanceof User);

        $ids = DB::table('favorites')
            ->where('user_id', $user->id)
            ->pluck('vehicle_id');

        return response()->json(['data' => $ids]);
    }

    public function store(Request $request, string $vehicleId): JsonResponse
    {
        $user = $request->user();
        \assert($user instanceof User);

        // Vérifie que le véhicule existe et est public
        $exists = Vehicle::query()
            ->where('id', $vehicleId)
            ->where('status', 'active')
            ->exists();

        if (! $exists) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        // Idempotent : insertOrIgnore
        DB::table('favorites')->insertOrIgnore([
            'user_id' => $user->id,
            'vehicle_id' => $vehicleId,
            'created_at' => now(),
        ]);

        Log::info('Favorite added', ['user_id' => $user->id, 'vehicle_id' => $vehicleId]);

        return response()->json(['favorited' => true]);
    }

    public function destroy(Request $request, string $vehicleId): JsonResponse
    {
        $user = $request->user();
        \assert($user instanceof User);

        DB::table('favorites')
            ->where('user_id', $user->id)
            ->where('vehicle_id', $vehicleId)
            ->delete();

        Log::info('Favorite removed', ['user_id' => $user->id, 'vehicle_id' => $vehicleId]);

        return response()->json(['favorited' => false]);
    }
}
