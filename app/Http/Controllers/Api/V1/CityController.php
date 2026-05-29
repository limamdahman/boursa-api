<?php
declare(strict_types=1);
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

class CityController extends Controller
{
    public function index(): JsonResponse
    {
        $json = Cache::remember('cities.all.json', 3600, function () {
            $cities = City::query()->orderBy('name_fr')->get(['id', 'name_fr', 'name_ar']);
            return ['data' => $cities->toArray()];
        });

        return response()->json($json);
    }
}
