<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Agency\AgencyProfileController;
use App\Http\Controllers\Api\Agency\AgencyVehicleController;
use App\Http\Controllers\Api\Agency\AgencyVehicleMediaController;
use App\Http\Controllers\Api\User\NotificationController;
use App\Http\Controllers\Api\User\UserVehicleController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\CityController;
use App\Http\Controllers\Api\V1\AgencyController as PublicAgencyController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SellerProfileController;
use App\Http\Controllers\Api\V1\ReverseGeocodeController;
use App\Http\Controllers\Api\V1\SellerFollowController;
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

    // Agencies (public)
    Route::get('/agencies', [PublicAgencyController::class, 'index']);
    Route::get('/geocode/reverse', [ReverseGeocodeController::class, 'reverse']);
    Route::get('/agencies/{slug}', [PublicAgencyController::class, 'show'])
        ->where('slug', '[a-zA-Z0-9\-]+');

    // Reviews (public read)
    Route::get('/sellers/users/{userId}', [SellerProfileController::class, 'show'])
        ->where('userId', '[0-9a-fA-F\-]{36}');
    Route::get('/sellers/{sellerType}/{sellerId}/followers-count', [SellerFollowController::class, 'followersCount'])
        ->where('sellerType', 'user|agency')
        ->where('sellerId', '[0-9a-fA-F\-]{36}');
    Route::get('/sellers/users/{userId}/reviews', [ReviewController::class, 'userReviews'])
        ->where('userId', '[0-9a-fA-F\-]{36}');
    Route::get('/sellers/users/{userId}/rating-summary', [ReviewController::class, 'userRatingSummary'])
        ->where('userId', '[0-9a-fA-F\-]{36}');
    Route::get('/sellers/agencies/{agencyId}/reviews', [ReviewController::class, 'agencyReviews'])
        ->where('agencyId', '[0-9a-fA-F\-]{36}');
    Route::get('/sellers/agencies/{agencyId}/rating-summary', [ReviewController::class, 'agencyRatingSummary'])
        ->where('agencyId', '[0-9a-fA-F\-]{36}');

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
        Route::put('/me', [AuthController::class, 'updateProfile']);
        Route::put('/me/password', [AuthController::class, 'updatePassword']);
        Route::post('/me/avatar', [App\Http\Controllers\Api\V1\AvatarController::class, 'upload']);

        // Véhicules agence
        Route::post('/agency/vehicles/{id}/sold', [App\Http\Controllers\Api\V1\VehicleStatusController::class, 'markSold']);
        Route::post('/agency/vehicles/{id}/price', [App\Http\Controllers\Api\V1\VehicleStatusController::class, 'updatePrice']);

        // Véhicules particulier
        Route::post('/me/vehicles/{id}/sold', [App\Http\Controllers\Api\V1\VehicleStatusController::class, 'markSoldUser']);
        Route::post('/me/vehicles/{id}/price', [App\Http\Controllers\Api\V1\VehicleStatusController::class, 'updatePriceUser']);

        // Mes véhicules (particuliers — auth required, pas de middleware role)
        Route::prefix('me/vehicles')->group(function () {
            Route::get('/', [UserVehicleController::class, 'index']);
            Route::post('/', [UserVehicleController::class, 'store']);
            Route::get('/{id}', [UserVehicleController::class, 'show'])
                ->where('id', '[0-9a-fA-F\-]{36}');
            Route::put('/{id}', [UserVehicleController::class, 'update'])
                ->where('id', '[0-9a-fA-F\-]{36}');
            Route::delete('/{id}', [UserVehicleController::class, 'destroy'])
                ->where('id', '[0-9a-fA-F\-]{36}');
            Route::post('/{id}/media', [UserVehicleController::class, 'uploadMedia'])
                ->where('id', '[0-9a-fA-F\-]{36}');
            Route::delete('/{vehicleId}/media/{mediaId}', [UserVehicleController::class, 'destroyMedia'])
                ->where('vehicleId', '[0-9a-fA-F\-]{36}')
                ->where('mediaId', '[0-9a-fA-F\-]{36}');
            Route::post('/{vehicleId}/media/{mediaId}/cover', [UserVehicleController::class, 'setCoverMedia'])
                ->where('vehicleId', '[0-9a-fA-F\-]{36}')
                ->where('mediaId', '[0-9a-fA-F\-]{36}');
        });

        // Reviews (submit + can)
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::post('/seller-follows/toggle', [SellerFollowController::class, 'toggle']);
        Route::get('/seller-follows/is-following', [SellerFollowController::class, 'isFollowing']);
        Route::get('/me/seller-follows', [SellerFollowController::class, 'myFollows']);
        Route::get('/vehicles/{id}/can-review', [ReviewController::class, 'canReview'])
            ->where('id', '[0-9a-fA-F\-]{36}');

        // Notifications
        Route::prefix('me/notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/recent', [NotificationController::class, 'recent']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        });

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

    // ── Chat ──
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/chat/conversations/{agencyId}', [App\Http\Controllers\Api\ChatController::class, 'getOrCreate']);
        Route::get('/chat/conversations/{conversationId}/messages', [App\Http\Controllers\Api\ChatController::class, 'messages']);
        Route::post('/chat/conversations/{conversationId}/messages', [App\Http\Controllers\Api\ChatController::class, 'send']);
        Route::post('/chat/support', [App\Http\Controllers\Api\ChatController::class, 'getOrCreateSupport']);
        Route::get('/chat/agency/conversations', [App\Http\Controllers\Api\ChatController::class, 'agencyConversations']);
    });
});

