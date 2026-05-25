<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\SubmitReviewRequest;
use App\Models\Lead;
use App\Models\Review;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(SubmitReviewRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $vehicle = Vehicle::find($data['vehicle_id']);
        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        // Empêcher de se reviewer soi-même
        $isOwnVehicle = ($vehicle->user_id === $user->id)
            || ($vehicle->agency && $vehicle->agency->user_id === $user->id);
        if ($isOwnVehicle) {
            return response()->json(['message' => 'Vous ne pouvez pas évaluer votre propre annonce.'], 403);
        }

        // Vérifier qu'un lead existe (= preuve de contact)
        $hasLead = Lead::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('sender_user_id', $user->id)
            ->exists();

        if (! $hasLead) {
            return response()->json([
                'message' => "Vous devez d'abord contacter le vendeur avant de laisser un avis.",
            ], 403);
        }

        // Identifier le seller
        $sellerUserId = $vehicle->agency_id ? null : $vehicle->user_id;
        $sellerAgencyId = $vehicle->agency_id;

        // Vérifier si un avis existe déjà
        $existing = Review::query()
            ->where('reviewer_user_id', $user->id)
            ->where(function ($q) use ($sellerUserId, $sellerAgencyId) {
                if ($sellerUserId) {
                    $q->where('seller_user_id', $sellerUserId);
                } else {
                    $q->where('seller_agency_id', $sellerAgencyId);
                }
            })
            ->first();

        if ($existing) {
            // Update au lieu de doublon
            $existing->update([
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'vehicle_id' => $vehicle->id,
            ]);
            return response()->json([
                'message' => 'Avis mis à jour.',
                'data' => $existing->fresh(),
            ]);
        }

        $review = Review::create([
            'reviewer_user_id' => $user->id,
            'seller_user_id' => $sellerUserId,
            'seller_agency_id' => $sellerAgencyId,
            'vehicle_id' => $vehicle->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Avis publié.',
            'data' => $review,
        ], 201);
    }

    public function userReviews(string $userId): JsonResponse
    {
        $reviews = Review::query()
            ->where('seller_user_id', $userId)
            ->with(['reviewer:id,name', 'vehicle:id,brand_id,vehicle_model_id,year'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($reviews);
    }

    public function agencyReviews(string $agencyId): JsonResponse
    {
        $reviews = Review::query()
            ->where('seller_agency_id', $agencyId)
            ->with(['reviewer:id,name', 'vehicle:id,brand_id,vehicle_model_id,year'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($reviews);
    }

    public function userRatingSummary(string $userId): JsonResponse
    {
        return response()->json([
            'count' => Review::where('seller_user_id', $userId)->count(),
            'average' => (float) Review::where('seller_user_id', $userId)->avg('rating'),
        ]);
    }

    public function agencyRatingSummary(string $agencyId): JsonResponse
    {
        return response()->json([
            'count' => Review::where('seller_agency_id', $agencyId)->count(),
            'average' => (float) Review::where('seller_agency_id', $agencyId)->avg('rating'),
        ]);
    }

    public function canReview(Request $request, string $vehicleId): JsonResponse
    {
        $user = $request->user();
        $vehicle = Vehicle::find($vehicleId);

        if (! $vehicle) {
            return response()->json(['can_review' => false, 'reason' => 'vehicle_not_found']);
        }

        $isOwnVehicle = ($vehicle->user_id === $user->id)
            || ($vehicle->agency && $vehicle->agency->user_id === $user->id);
        if ($isOwnVehicle) {
            return response()->json(['can_review' => false, 'reason' => 'own_vehicle']);
        }

        $hasLead = Lead::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('sender_user_id', $user->id)
            ->exists();

        if (! $hasLead) {
            return response()->json(['can_review' => false, 'reason' => 'no_lead']);
        }

        // Récupérer review existante si elle existe
        $sellerUserId = $vehicle->agency_id ? null : $vehicle->user_id;
        $sellerAgencyId = $vehicle->agency_id;

        $existing = Review::query()
            ->where('reviewer_user_id', $user->id)
            ->where(function ($q) use ($sellerUserId, $sellerAgencyId) {
                if ($sellerUserId) {
                    $q->where('seller_user_id', $sellerUserId);
                } else {
                    $q->where('seller_agency_id', $sellerAgencyId);
                }
            })
            ->first();

        return response()->json([
            'can_review' => true,
            'existing_review' => $existing ? [
                'rating' => $existing->rating,
                'comment' => $existing->comment,
            ] : null,
        ]);
    }
}
