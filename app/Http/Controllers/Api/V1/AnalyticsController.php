<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Leads\TrackViewContentAction;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AnalyticsController extends Controller
{
    public function trackView(Request $request, string $vehicleId, TrackViewContentAction $action): JsonResponse
    {
        $vehicle = Vehicle::with(['brand', 'vehicleModel'])
            ->where('status', VehicleStatus::ACTIVE->value)
            ->find($vehicleId);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        // Limite: 1 ViewContent par IP+vehicle / 5 minutes (dedup naturel)
        $rateKey = sprintf('view:%s:%s', $request->ip(), $vehicleId);
        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            return response()->json(['tracked' => false, 'reason' => 'recent_view']);
        }
        RateLimiter::hit($rateKey, 300);

        $action->execute(
            vehicle: $vehicle,
            request: $request,
            user: $request->user(),
            eventId: $request->input('event_id'),
        );

        return response()->json(['tracked' => true]);
    }
}
