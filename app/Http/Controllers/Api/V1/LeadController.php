<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Leads\SubmitLeadAction;
use App\Enums\LeadType;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\SubmitLeadRequest;
use App\Models\Vehicle;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class LeadController extends Controller
{
    public function store(SubmitLeadRequest $request, string $vehicleId, SubmitLeadAction $action): JsonResponse
    {
        $vehicle = Vehicle::with(['brand', 'vehicleModel'])
            ->where('status', VehicleStatus::ACTIVE->value)
            ->find($vehicleId);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        $rateKey = sprintf(
            'lead:%s:%s',
            $request->ip(),
            $vehicleId
        );

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return response()->json([
                'message' => sprintf('Trop de tentatives. Réessayez dans %d secondes.', $seconds),
            ], 429);
        }

        RateLimiter::hit($rateKey, 600);

        $data = $request->validated();
        $leadType = LeadType::from($data['type']);

        try {
            $lead = $action->execute(
                vehicle: $vehicle,
                type: $leadType,
                request: $request,
                user: $request->user(),
                message: $data['message'] ?? null,
                senderName: $data['sender_name'] ?? null,
                senderPhone: $request->normalizedPhone(),
                eventId: $data['event_id'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Contact enregistré.',
            'lead_id' => $lead->id,
            'type' => $lead->type,
        ], 201);
    }
}
