#!/usr/bin/env bash
# Boursa — CRUD agence (sous-étape 2)
# Endpoints agence: profile, vehicles CRUD, media upload/reorder/delete, publish
#
# Usage depuis ~/projects/boursa/boursa-api :
#   bash scripts/create_agency_endpoints.sh

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "==> 1. FormRequests"
mkdir -p app/Http/Requests/Agencies
mkdir -p app/Http/Requests/Vehicles

cat > app/Http/Requests/Agencies/UpdateAgencyRequest.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Requests\Agencies;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAgency();
    }

    public function rules(): array
    {
        $agencyId = $this->user()->agency?->id;

        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:500'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'rc_number' => ['nullable', 'string', 'max:50'],
            'phone_whatsapp' => ['nullable', 'string', 'max:20'],
            'phone_call' => ['nullable', 'string', 'max:20'],
            'email' => [
                'nullable', 'email', 'max:150',
                Rule::unique('agencies', 'email')->ignore($agencyId),
            ],
            'website' => ['nullable', 'url', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
EOF

cat > app/Http/Requests/Vehicles/StoreVehicleRequest.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAgency();
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'vehicle_model_id' => ['required', 'integer', 'exists:vehicle_models,id'],
            'year' => ['required', 'integer', 'min:1980', 'max:2030'],
            'mileage_km' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'price_mru' => ['required', 'integer', 'min:50000'],
            'price_negotiable' => ['nullable', 'boolean'],
            'fuel' => ['nullable', 'in:gasoline,diesel,hybrid,electric,lpg'],
            'transmission' => ['nullable', 'in:manual,automatic'],
            'body_type' => ['nullable', 'in:sedan,suv,pickup,hatchback,van,coupe'],
            'color' => ['nullable', 'string', 'max:30'],
            'condition' => ['nullable', 'in:new,used,imported'],
            'description_fr' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'specs' => ['nullable', 'array'],
            'specs.options' => ['nullable', 'array'],
            'specs.options.*' => ['string', 'max:50'],
            'specs.doors' => ['nullable', 'integer', 'min:2', 'max:7'],
            'specs.seats' => ['nullable', 'integer', 'min:2', 'max:15'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
EOF

cat > app/Http/Requests/Vehicles/UpdateVehicleRequest.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAgency();
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['sometimes', 'integer', 'exists:brands,id'],
            'vehicle_model_id' => ['sometimes', 'integer', 'exists:vehicle_models,id'],
            'year' => ['sometimes', 'integer', 'min:1980', 'max:2030'],
            'mileage_km' => ['nullable', 'integer', 'min:0', 'max:2000000'],
            'price_mru' => ['sometimes', 'integer', 'min:50000'],
            'price_negotiable' => ['nullable', 'boolean'],
            'fuel' => ['nullable', 'in:gasoline,diesel,hybrid,electric,lpg'],
            'transmission' => ['nullable', 'in:manual,automatic'],
            'body_type' => ['nullable', 'in:sedan,suv,pickup,hatchback,van,coupe'],
            'color' => ['nullable', 'string', 'max:30'],
            'condition' => ['nullable', 'in:new,used,imported'],
            'description_fr' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'specs' => ['nullable', 'array'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
EOF

cat > app/Http/Requests/Vehicles/UploadVehicleMediaRequest.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class UploadVehicleMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAgency();
    }

    public function rules(): array
    {
        return [
            'photo' => [
                'required', 'file', 'image',
                'mimes:jpg,jpeg,png,webp',
                'max:15360',
            ],
            'is_cover' => ['nullable', 'boolean'],
        ];
    }
}
EOF

cat > app/Http/Requests/Vehicles/ReorderVehicleMediaRequest.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class ReorderVehicleMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAgency();
    }

    public function rules(): array
    {
        return [
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['required', 'string', 'uuid'],
        ];
    }
}
EOF

echo "==> 2. Policy VehiclePolicy (agency = ses véhicules; admin = tout)"
mkdir -p app/Policies
cat > app/Policies/VehiclePolicy.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $this->isOwner($user, $vehicle);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $this->isOwner($user, $vehicle);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $this->isOwner($user, $vehicle);
    }

    public function publish(User $user, Vehicle $vehicle): bool
    {
        return $this->isOwner($user, $vehicle);
    }

    public function manageMedia(User $user, Vehicle $vehicle): bool
    {
        return $this->isOwner($user, $vehicle);
    }

    private function isOwner(User $user, Vehicle $vehicle): bool
    {
        $agencyId = $user->agency?->id;

        return $agencyId !== null && $vehicle->agency_id === $agencyId;
    }
}
EOF

echo "==> 3. Action CreateVehicleAction (transactionnel + géoloc)"
mkdir -p app/Actions/Vehicles
cat > app/Actions/Vehicles/CreateVehicleAction.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Actions\Vehicles;

use App\Enums\VehicleStatus;
use App\Models\Agency;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

final class CreateVehicleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Agency $agency, array $data): Vehicle
    {
        return DB::transaction(function () use ($agency, $data): Vehicle {
            $lat = $data['latitude'] ?? null;
            $lng = $data['longitude'] ?? null;
            unset($data['latitude'], $data['longitude']);

            $vehicle = Vehicle::create(array_merge($data, [
                'agency_id' => $agency->id,
                'user_id' => $agency->user_id,
                'status' => VehicleStatus::DRAFT,
            ]));

            if ($lat !== null && $lng !== null) {
                DB::statement(
                    'UPDATE vehicles SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                    [(float) $lng, (float) $lat, $vehicle->id]
                );
            }

            return $vehicle->fresh()->load(['brand', 'vehicleModel', 'city']);
        });
    }
}
EOF

cat > app/Actions/Vehicles/UpdateVehicleAction.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Actions\Vehicles;

use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

final class UpdateVehicleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Vehicle $vehicle, array $data): Vehicle
    {
        return DB::transaction(function () use ($vehicle, $data): Vehicle {
            $lat = $data['latitude'] ?? null;
            $lng = $data['longitude'] ?? null;
            unset($data['latitude'], $data['longitude']);

            if (! empty($data)) {
                $vehicle->update($data);
            }

            if ($lat !== null && $lng !== null) {
                DB::statement(
                    'UPDATE vehicles SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                    [(float) $lng, (float) $lat, $vehicle->id]
                );
            }

            return $vehicle->fresh()->load(['brand', 'vehicleModel', 'city', 'media']);
        });
    }
}
EOF

cat > app/Actions/Vehicles/PublishVehicleAction.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Actions\Vehicles;

use App\Enums\VehicleStatus;
use App\Models\Vehicle;
use DomainException;

final class PublishVehicleAction
{
    /**
     * Soumet un véhicule à modération.
     * En MVP: passe direct à ACTIVE si l'agence est verified (à durcir plus tard).
     */
    public function execute(Vehicle $vehicle): Vehicle
    {
        if ($vehicle->status === VehicleStatus::ACTIVE) {
            throw new DomainException('Véhicule déjà publié.');
        }

        if ($vehicle->media()->count() === 0) {
            throw new DomainException('Au moins une photo est requise pour publier.');
        }

        $agency = $vehicle->agency;
        if (! $agency) {
            throw new DomainException('Agence introuvable.');
        }

        $isVerified = $agency->isVerified();

        $vehicle->update([
            'status' => $isVerified ? VehicleStatus::ACTIVE : VehicleStatus::PENDING,
            'published_at' => $isVerified ? now() : null,
            'expires_at' => $isVerified ? now()->addMonths(2) : null,
        ]);

        return $vehicle->fresh();
    }
}
EOF

echo "==> 4. Resources Agency"
mkdir -p app/Http/Resources/Agency
cat > app/Http/Resources/Agency/AgencyProfileResource.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Resources\Agency;

use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agency
 */
class AgencyProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_url,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->whenLoaded('city', fn () => $this->city ? [
                'id' => $this->city->id,
                'name_fr' => $this->city->name_fr,
            ] : null),
            'rc_number' => $this->rc_number,
            'phone_whatsapp' => $this->phone_whatsapp,
            'phone_call' => $this->phone_call,
            'email' => $this->email,
            'website' => $this->website,
            'status' => $this->status,
            'is_verified' => $this->isVerified(),
            'verified_at' => $this->verified_at?->toIso8601String(),
            'subscription_tier' => $this->subscription_tier,
            'quota_active_listings' => $this->quota_active_listings,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
EOF

cat > app/Http/Resources/Agency/AgencyVehicleResource.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Resources\Agency;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class AgencyVehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ]),
            'model' => $this->whenLoaded('vehicleModel', fn () => [
                'id' => $this->vehicleModel->id,
                'name' => $this->vehicleModel->name,
            ]),
            'year' => $this->year,
            'mileage_km' => $this->mileage_km,
            'price_mru' => $this->price_mru,
            'price_negotiable' => $this->price_negotiable,
            'fuel' => $this->fuel,
            'transmission' => $this->transmission,
            'body_type' => $this->body_type,
            'color' => $this->color,
            'condition' => $this->condition,
            'description_fr' => $this->description_fr,
            'description_ar' => $this->description_ar,
            'specs' => $this->specs,
            'status' => $this->status,
            'moderation_notes' => $this->moderation_notes,
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->id,
                'url_thumb' => $m->url_thumb,
                'url_md' => $m->url_webp_md,
                'url_original' => $m->url_original,
                'is_cover' => $m->is_cover,
                'watermarked' => $m->watermarked,
                'sort_order' => $m->sort_order,
            ])),
            'stats' => [
                'views_count' => $this->views_count,
                'contacts_count' => $this->contacts_count,
            ],
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
EOF

echo "==> 5. AgencyProfileController"
mkdir -p app/Http/Controllers/Api/Agency
cat > app/Http/Controllers/Api/Agency/AgencyProfileController.php << 'EOF'
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
EOF

echo "==> 6. AgencyVehicleController (CRUD complet)"
cat > app/Http/Controllers/Api/Agency/AgencyVehicleController.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agency;

use App\Actions\Vehicles\CreateVehicleAction;
use App\Actions\Vehicles\PublishVehicleAction;
use App\Actions\Vehicles\UpdateVehicleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\StoreVehicleRequest;
use App\Http\Requests\Vehicles\UpdateVehicleRequest;
use App\Http\Resources\Agency\AgencyVehicleResource;
use App\Models\Vehicle;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AgencyVehicleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $agency = $request->user()->agency;

        if (! $agency) {
            return response()->json(['message' => 'Aucune agence.'], 404);
        }

        $query = $agency->vehicles()
            ->with(['brand', 'vehicleModel', 'media' => fn ($q) => $q->orderBy('sort_order')])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return AgencyVehicleResource::collection($query->paginate(20));
    }

    public function show(Request $request, string $id): AgencyVehicleResource|JsonResponse
    {
        $vehicle = Vehicle::with(['brand', 'vehicleModel', 'city', 'media' => fn ($q) => $q->orderBy('sort_order')])
            ->find($id);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        if (! $request->user()->can('view', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        return new AgencyVehicleResource($vehicle);
    }

    public function store(StoreVehicleRequest $request, CreateVehicleAction $action): AgencyVehicleResource|JsonResponse
    {
        $agency = $request->user()->agency;

        if (! $agency) {
            return response()->json(['message' => 'Aucune agence.'], 404);
        }

        $count = $agency->vehicles()->whereIn('status', ['active', 'pending'])->count();
        if ($count >= $agency->quota_active_listings) {
            return response()->json([
                'message' => sprintf('Quota atteint (%d annonces actives max).', $agency->quota_active_listings),
            ], 403);
        }

        $vehicle = $action->execute($agency, $request->validated());

        return new AgencyVehicleResource($vehicle);
    }

    public function update(UpdateVehicleRequest $request, string $id, UpdateVehicleAction $action): AgencyVehicleResource|JsonResponse
    {
        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        if (! $request->user()->can('update', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $vehicle = $action->execute($vehicle, $request->validated());

        return new AgencyVehicleResource($vehicle);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        if (! $request->user()->can('delete', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Véhicule supprimé.']);
    }

    public function publish(Request $request, string $id, PublishVehicleAction $action): AgencyVehicleResource|JsonResponse
    {
        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        if (! $request->user()->can('publish', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        try {
            $vehicle = $action->execute($vehicle);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new AgencyVehicleResource($vehicle->load(['brand', 'vehicleModel', 'media']));
    }
}
EOF

echo "==> 7. AgencyVehicleMediaController (upload, delete, reorder)"
cat > app/Http/Controllers/Api/Agency/AgencyVehicleMediaController.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\ReorderVehicleMediaRequest;
use App\Http\Requests\Vehicles\UploadVehicleMediaRequest;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use App\Services\Media\MediaUploadService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgencyVehicleMediaController extends Controller
{
    public function store(UploadVehicleMediaRequest $request, string $vehicleId, MediaUploadService $service): JsonResponse
    {
        $vehicle = Vehicle::find($vehicleId);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        if (! $request->user()->can('manageMedia', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        try {
            $media = $service->store(
                $vehicle,
                $request->file('photo'),
                (bool) $request->boolean('is_cover')
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Photo uploadée. Traitement asynchrone en cours.',
            'media' => [
                'id' => $media->id,
                'url_original' => $media->url_original,
                'is_cover' => $media->is_cover,
                'watermarked' => $media->watermarked,
                'sort_order' => $media->sort_order,
            ],
        ], 201);
    }

    public function destroy(Request $request, string $vehicleId, string $mediaId, MediaUploadService $service): JsonResponse
    {
        $vehicle = Vehicle::find($vehicleId);
        $media = VehicleMedia::where('id', $mediaId)->where('vehicle_id', $vehicleId)->first();

        if (! $vehicle || ! $media) {
            return response()->json(['message' => 'Ressource introuvable.'], 404);
        }

        if (! $request->user()->can('manageMedia', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $wasCover = $media->is_cover;
        $service->delete($media);

        if ($wasCover) {
            $next = $vehicle->media()->orderBy('sort_order')->first();
            if ($next) {
                $next->update(['is_cover' => true]);
            }
        }

        return response()->json(['message' => 'Photo supprimée.']);
    }

    public function reorder(ReorderVehicleMediaRequest $request, string $vehicleId): JsonResponse
    {
        $vehicle = Vehicle::find($vehicleId);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        if (! $request->user()->can('manageMedia', $vehicle)) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $order = $request->validated()['order'];

        DB::transaction(function () use ($vehicle, $order): void {
            foreach ($order as $idx => $mediaId) {
                $vehicle->media()->where('id', $mediaId)->update(['sort_order' => $idx]);
            }
        });

        return response()->json(['message' => 'Ordre mis à jour.']);
    }
}
EOF

echo "==> 8. Routes API mise à jour"
cat > routes/api.php << 'EOF'
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Agency\AgencyProfileController;
use App\Http\Controllers\Api\Agency\AgencyVehicleController;
use App\Http\Controllers\Api\Agency\AgencyVehicleMediaController;
use App\Http\Controllers\Api\V1\AuthController;
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
    Route::prefix('vehicles')->group(function () {
        Route::get('/', [VehicleController::class, 'index']);
        Route::get('/{id}', [VehicleController::class, 'show'])
            ->where('id', '[0-9a-fA-F\-]{36}');
        Route::get('/{id}/similar', [VehicleController::class, 'similar'])
            ->where('id', '[0-9a-fA-F\-]{36}');
    });

    // Authentifié
    Route::middleware('auth:sanctum')->group(function () {
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
EOF

echo "==> 9. Enregistrement de la policy dans AppServiceProvider"
python3 - << 'PYEOF'
from pathlib import Path

path = Path('app/Providers/AppServiceProvider.php')
content = path.read_text()

if 'VehiclePolicy' in content:
    print('  → Policy déjà enregistrée.')
else:
    # Ajouter le use statement
    if 'use App\\Services\\Sms\\SmsManager;' in content:
        content = content.replace(
            'use App\\Services\\Sms\\SmsManager;',
            'use App\\Models\\Vehicle;\nuse App\\Policies\\VehiclePolicy;\nuse App\\Services\\Sms\\SmsManager;\nuse Illuminate\\Support\\Facades\\Gate;'
        )

    # Ajouter la registration dans boot()
    content = content.replace(
        'public function boot(): void {}',
        '''public function boot(): void
    {
        Gate::policy(Vehicle::class, VehiclePolicy::class);
    }'''
    )

    path.write_text(content)
    print('  → VehiclePolicy enregistrée.')
PYEOF

echo ""
echo "==> ✅ CRUD agence généré"
echo ""
echo "Prochaines commandes :"
echo "  php -l app/Http/Controllers/Api/Agency/AgencyVehicleController.php"
echo "  php -l app/Http/Controllers/Api/Agency/AgencyVehicleMediaController.php"
echo "  php artisan route:list --path=api/v1/agency"
