<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleStatusController extends Controller
{
    public function markSold(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::where('id', $id)
            ->where('agency_id', $request->user()?->agency?->id)
            ->firstOrFail();

        $vehicle->update(['status' => VehicleStatus::SOLD]);
        return response()->json(['status' => 'sold']);
    }

    public function updatePrice(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'price'    => 'required|integer|min:0',
            'is_deal'  => 'boolean',
        ]);

        $vehicle = Vehicle::where('id', $id)
            ->where('agency_id', $request->user()?->agency?->id)
            ->firstOrFail();

        $vehicle->update([
            'original_price' => $vehicle->original_price ?? $vehicle->price,
            'price'          => $request->price,
            'is_deal'        => $request->boolean('is_deal', true),
        ]);

        return response()->json(['price' => $vehicle->price, 'original_price' => $vehicle->original_price]);
    }

    public function markSoldUser(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->whereNull('agency_id')
            ->firstOrFail();

        $vehicle->update(['status' => VehicleStatus::SOLD]);
        return response()->json(['status' => 'sold']);
    }

    public function updatePriceUser(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'price'   => 'required|integer|min:0',
            'is_deal' => 'boolean',
        ]);

        $vehicle = Vehicle::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->whereNull('agency_id')
            ->firstOrFail();

        $vehicle->update([
            'original_price' => $vehicle->original_price ?? $vehicle->price,
            'price'          => $request->price,
            'is_deal'        => $request->boolean('is_deal', true),
        ]);

        return response()->json(['price' => $vehicle->price, 'original_price' => $vehicle->original_price]);
    }
}
