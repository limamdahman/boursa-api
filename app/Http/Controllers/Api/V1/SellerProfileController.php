<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\VehicleListResource;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class SellerProfileController extends Controller
{
    public function show(string $userId): JsonResponse
    {
        $user = User::find($userId);
        if (! $user) {
            return response()->json(['message' => 'Utilisateur introuvable.'], 404);
        }

        // Compter les annonces actives
        $activeCount = Vehicle::where('user_id', $user->id)
            ->whereNull('agency_id')
            ->where('status', VehicleStatus::ACTIVE->value)
            ->count();

        // Récupérer les annonces actives avec relations
        $vehicles = Vehicle::with(['brand:id,name,slug', 'vehicleModel:id,name', 'city:id,name_fr,name_ar', 'coverMedia'])
            ->where('user_id', $user->id)
            ->whereNull('agency_id')
            ->where('status', VehicleStatus::ACTIVE->value)
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone_whatsapp' => $user->phone,
                'phone_call' => $user->phone,
                'member_since' => $user->created_at?->toIso8601String(),
                'avatar_url' => $user->avatar_url,
                'active_listings_count' => $activeCount,
                'vehicles' => VehicleListResource::collection($vehicles),
            ],
        ]);
    }
}
