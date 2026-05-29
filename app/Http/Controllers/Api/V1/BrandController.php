<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\VehicleModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function index(): JsonResponse
    {
        $json = Cache::remember('brands.all.json', 3600, function () {
            $brands = Brand::query()->orderBy('name')->get(['id', 'name', 'slug']);
            return ['data' => $brands->toArray()];
        });

        return response()->json($json);
    }

    public function models(int $brandId): JsonResponse
    {
        $json = Cache::remember("brands.{$brandId}.models.json", 3600, function () use ($brandId) {
            $models = VehicleModel::query()
                ->where('brand_id', $brandId)
                ->orderBy('name')
                ->get(['id', 'brand_id', 'name']);
            return ['data' => $models->toArray()];
        });

        return response()->json($json);
    }
}
