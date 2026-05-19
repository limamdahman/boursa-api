<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\VehicleModel;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        $brands = Brand::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json(['data' => $brands]);
    }

    public function models(int $brandId): JsonResponse
    {
        $models = VehicleModel::query()
            ->where('brand_id', $brandId)
            ->orderBy('name')
            ->get(['id', 'brand_id', 'name']);

        return response()->json(['data' => $models]);
    }
}
