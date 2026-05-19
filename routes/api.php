<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Agency\AgencyProfileController;
use App\Http\Controllers\Api\Agency\AgencyVehicleController;
use App\Http\Controllers\Api\Agency\AgencyVehicleMediaController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]));

    // Auth (public)
    Route::prefix('auth')->group(function () {
        Route::post('/otp/send', [AuthController::class, 'sendOtp']);
        Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    // Vehicles (public)
    Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/brands/{id}/models', [BrandController::class, 'models'])->where('id', '[0-9]+');
    Route::get('/cities', [CityController::class, 'index']);

    Route::prefix('vehicles')->group(function () {
        Route::get('/', [VehicleController::class, 'index']);
        Route::get('/{id}', [VehicleController::class, 'show'])
            ->where('id', '[0-9a-fA-F\-]{36}');
        Route::get('/{id}/similar', [VehicleController::class, 'similar'])
            ->where('id', '[0-9a-fA-F\-]{36}');

        Route::post('/{id}/lead', [LeadController::class, 'store'])
            ->where('id', '[0-9a-fA-F\-]{36}');

        Route::post('/{id}/track-view', [AnalyticsController::class, 'trackView'])
            ->where('id', '[0-9a-fA-F\-]{36}');
    });

    // Authentifié
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::get('/favorites/ids', [FavoriteController::class, 'ids']);
        Route::post('/favorites/{vehicleId}', [FavoriteController::class, 'store'])
            ->where('vehicleId', '[0-9a-fA-F-]{36}');
        Route::delete('/favorites/{vehicleId}', [FavoriteController::class, 'destroy'])
            ->where('vehicleId', '[0-9a-fA-F-]{36}');

        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout/all', [AuthController::class, 'logoutAll']);

        Route::get('/me', fn (Request $request) => $request->user()->load('agency'));

        // Admin
        Route::middleware('role.boursa:admin')->prefix('admin')->group(function () {
            Route::get('/dashboard', fn (Request $request) => response()->json([
                'message' => 'Welcome admin',
                'permissions' => $request->user()->getAllPermissions()->pluck('name'),
            ]));
        });

        // Agency
        Route::middleware('role.boursa:agency')->prefix('agency')->group(function () {
            Route::get('/profile', [AgencyProfileController::class, 'show']);
            Route::put('/profile', [AgencyProfileController::class, 'update']);

            Route::get('/brands', [BrandController::class, 'index']);
    Route::get('/brands/{id}/models', [BrandController::class, 'models'])->where('id', '[0-9]+');
    Route::get('/cities', [CityController::class, 'index']);

    Route::prefix('vehicles')->group(function () {
                Route::get('/', [AgencyVehicleController::class, 'index']);
                Route::post('/', [AgencyVehicleController::class, 'store']);
                Route::get('/{id}', [AgencyVehicleController::class, 'show'])
                    ->where('id', '[0-9a-fA-F\-]{36}');
                Route::put('/{id}', [AgencyVehicleController::class, 'update'])
                    ->where('id', '[0-9a-fA-F\-]{36}');
                Route::delete('/{id}', [AgencyVehicleController::class, 'destroy'])
                    ->where('id', '[0-9a-fA-F\-]{36}');
                Route::post('/{id}/publish', [AgencyVehicleController::class, 'publish'])
                    ->where('id', '[0-9a-fA-F\-]{36}');

                Route::post('/{id}/media', [AgencyVehicleMediaController::class, 'store'])
                    ->where('id', '[0-9a-fA-F\-]{36}');
                Route::patch('/{id}/media/reorder', [AgencyVehicleMediaController::class, 'reorder'])
                    ->where('id', '[0-9a-fA-F\-]{36}');
                Route::delete('/{vehicleId}/media/{mediaId}', [AgencyVehicleMediaController::class, 'destroy'])
                    ->where('vehicleId', '[0-9a-fA-F\-]{36}')
                    ->where('mediaId', '[0-9a-fA-F\-]{36}');
            });
        });
    });
});
