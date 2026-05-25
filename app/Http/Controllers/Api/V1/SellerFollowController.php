<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\SellerFollow;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerFollowController extends Controller
{
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seller_type' => ['required', 'in:user,agency'],
            'seller_id' => ['required', 'string', 'uuid'],
        ]);

        $follower = $request->user();

        // Empêcher de se suivre soi-même
        if ($validated['seller_type'] === 'user' && $validated['seller_id'] === $follower->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous suivre vous-même.'], 422);
        }

        // Vérifier existence
        if ($validated['seller_type'] === 'user') {
            if (! User::where('id', $validated['seller_id'])->exists()) {
                return response()->json(['message' => 'Vendeur introuvable.'], 404);
            }
            $criteria = ['seller_user_id' => $validated['seller_id'], 'seller_agency_id' => null];
        } else {
            if (! Agency::where('id', $validated['seller_id'])->exists()) {
                return response()->json(['message' => 'Agence introuvable.'], 404);
            }
            $criteria = ['seller_agency_id' => $validated['seller_id'], 'seller_user_id' => null];
        }

        $existing = SellerFollow::where('follower_user_id', $follower->id)
            ->where($criteria)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['following' => false, 'message' => 'Vendeur non suivi.']);
        }

        SellerFollow::create(array_merge(['follower_user_id' => $follower->id], $criteria));
        return response()->json(['following' => true, 'message' => 'Vendeur suivi.'], 201);
    }

    public function isFollowing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seller_type' => ['required', 'in:user,agency'],
            'seller_id' => ['required', 'string', 'uuid'],
        ]);

        $criteria = $validated['seller_type'] === 'user'
            ? ['seller_user_id' => $validated['seller_id'], 'seller_agency_id' => null]
            : ['seller_agency_id' => $validated['seller_id'], 'seller_user_id' => null];

        $following = SellerFollow::where('follower_user_id', $request->user()->id)
            ->where($criteria)
            ->exists();

        return response()->json(['following' => $following]);
    }

    public function followersCount(string $sellerType, string $sellerId): JsonResponse
    {
        if (! in_array($sellerType, ['user', 'agency'], true)) {
            return response()->json(['message' => 'Type invalide.'], 422);
        }

        $column = $sellerType === 'user' ? 'seller_user_id' : 'seller_agency_id';
        $count = SellerFollow::where($column, $sellerId)->count();

        return response()->json(['count' => $count]);
    }

    public function myFollows(Request $request): JsonResponse
    {
        $follows = SellerFollow::where('follower_user_id', $request->user()->id)
            ->with(['sellerUser:id,name', 'sellerAgency:id,name,slug,logo_url'])
            ->orderByDesc('created_at')
            ->paginate(20);

        // Pour chaque follow, récupérer les véhicules actifs récents + total count
        $data = $follows->getCollection()->map(function ($follow) {
            $sellerType = $follow->seller_agency_id ? 'agency' : 'user';
            $sellerId = $follow->seller_agency_id ?: $follow->seller_user_id;
            $sellerName = $follow->seller_agency_id
                ? ($follow->sellerAgency?->name ?? '—')
                : ($follow->sellerUser?->name ?? '—');
            $sellerSlug = $follow->sellerAgency?->slug;
            $logoUrl = $follow->sellerAgency?->logo_url;

            // Construire query véhicules actifs
            $vehiclesQuery = \App\Models\Vehicle::query()
                ->with(['brand:id,name,slug', 'vehicleModel:id,name', 'city:id,name_fr,name_ar', 'coverMedia'])
                ->where('status', \App\Enums\VehicleStatus::ACTIVE->value);

            if ($sellerType === 'agency') {
                $vehiclesQuery->where('agency_id', $sellerId);
            } else {
                $vehiclesQuery->whereNull('agency_id')->where('user_id', $sellerId);
            }

            $total = (clone $vehiclesQuery)->count();
            $recent = $vehiclesQuery->orderByDesc('published_at')->limit(3)->get();

            return [
                'id' => $follow->id,
                'followed_at' => $follow->created_at?->toIso8601String(),
                'seller_type' => $sellerType,
                'seller_id' => $sellerId,
                'seller_name' => $sellerName,
                'seller_slug' => $sellerSlug,
                'seller_logo_url' => $logoUrl,
                'profile_url' => $sellerType === 'agency'
                    ? '/agences/' . $sellerSlug
                    : '/profil/' . $sellerId,
                'active_vehicles_count' => $total,
                'recent_vehicles' => \App\Http\Resources\V1\VehicleListResource::collection($recent),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $follows->currentPage(),
                'last_page' => $follows->lastPage(),
                'per_page' => $follows->perPage(),
                'total' => $follows->total(),
            ],
        ]);
    }
}
