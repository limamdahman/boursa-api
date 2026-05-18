<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agencies\UpdateAgencyRequest;
use App\Http\Resources\Agency\AgencyProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgencyProfileController extends Controller
{
    public function show(Request $request): AgencyProfileResource|JsonResponse
    {
        $agency = $request->user()->agency()->with('city')->first();

        if (! $agency) {
            return response()->json([
                'message' => 'Aucune agence associée à votre compte.',
            ], 404);
        }

        return new AgencyProfileResource($agency);
    }

    public function update(UpdateAgencyRequest $request): AgencyProfileResource|JsonResponse
    {
        $agency = $request->user()->agency;

        if (! $agency) {
            return response()->json([
                'message' => 'Aucune agence associée à votre compte.',
            ], 404);
        }

        $data = $request->validated();
        $lat = $data['latitude'] ?? null;
        $lng = $data['longitude'] ?? null;
        unset($data['latitude'], $data['longitude']);

        if (! empty($data)) {
            $agency->update($data);
        }

        if ($lat !== null && $lng !== null) {
            DB::statement(
                'UPDATE agencies SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                [(float) $lng, (float) $lat, $agency->id]
            );
        }

        return new AgencyProfileResource($agency->fresh()->load('city'));
    }
}
