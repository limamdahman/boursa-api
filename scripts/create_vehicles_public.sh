#!/usr/bin/env bash
# Création complète des endpoints publics véhicules Boursa
# Usage depuis ~/projects/boursa/boursa-api :
#   bash scripts/create_vehicles_public.sh
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "==> 1. Factory UserFactory (réécriture)"
cat > database/factories/UserFactory.php << 'EOF'
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => '222'.$this->faker->unique()->numerify('########'),
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::USER,
            'language' => 'fr',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
    }
}
EOF

echo "==> 2. Factory AgencyFactory"
cat > database/factories/AgencyFactory.php << 'EOF'
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AgencyStatus;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Auto Sahara', 'Nouakchott Motors', 'Mauritanie Auto', 'Saharian Cars',
            'Atlas Auto', 'Tevragh Auto', 'Premium MR Cars', 'Boursa Garage',
            'Sahel Motors', 'Capital Auto MR', 'Desert Wheels', 'AlAmin Motors',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::AGENCY,
            'name' => $name.' Manager',
        ]);

        return [
            'user_id' => $user->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'logo_url' => null,
            'description' => $this->faker->sentence(15),
            'address' => $this->faker->streetAddress().', Nouakchott',
            'city_id' => City::where('region', 'Nouakchott')->inRandomOrder()->first()?->id,
            'rc_number' => 'RC-'.$this->faker->numerify('######'),
            'phone_whatsapp' => '222'.$this->faker->numerify('########'),
            'phone_call' => '222'.$this->faker->numerify('########'),
            'email' => $this->faker->companyEmail(),
            'status' => AgencyStatus::VERIFIED,
            'verified_at' => now()->subMonths($this->faker->numberBetween(1, 18)),
            'subscription_tier' => $this->faker->randomElement(['free', 'pro', 'premium']),
            'quota_active_listings' => 50,
        ];
    }

    public function configure(): self
    {
        return $this->afterCreating(function (Agency $agency): void {
            $lat = 18.0735 + $this->faker->randomFloat(4, -0.05, 0.05);
            $lng = -15.9785 + $this->faker->randomFloat(4, -0.05, 0.05);

            DB::statement(
                'UPDATE agencies SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                [$lng, $lat, $agency->id]
            );

            $agency->user->syncRoles([UserRole::AGENCY->value]);
        });
    }
}
EOF

echo "==> 3. Factory VehicleFactory"
cat > database/factories/VehicleFactory.php << 'EOF'
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VehicleStatus;
use App\Models\Brand;
use App\Models\City;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        $brand = Brand::inRandomOrder()->first() ?? Brand::factory()->create();
        $model = VehicleModel::where('brand_id', $brand->id)->inRandomOrder()->first()
            ?? VehicleModel::factory()->for($brand)->create();
        $city = City::inRandomOrder()->first();

        $year = $this->faker->numberBetween(2008, 2025);
        $age = 2026 - $year;
        $basePrice = $this->faker->numberBetween(800_000, 8_500_000);
        $depreciation = max(0.3, 1 - ($age * 0.07));
        $price = (int) round($basePrice * $depreciation, -4);

        $fuel = $this->faker->randomElement(['gasoline', 'diesel', 'diesel', 'diesel', 'hybrid']);
        $bodyType = $this->faker->randomElement(['sedan', 'suv', 'pickup', 'suv', 'pickup', 'hatchback']);
        $transmission = $this->faker->randomElement(['manual', 'automatic', 'automatic']);

        return [
            'brand_id' => $brand->id,
            'vehicle_model_id' => $model->id,
            'year' => $year,
            'mileage_km' => $this->faker->numberBetween(15_000, 280_000),
            'price_mru' => $price,
            'price_negotiable' => $this->faker->boolean(70),
            'currency' => 'MRU',
            'fuel' => $fuel,
            'transmission' => $transmission,
            'body_type' => $bodyType,
            'color' => $this->faker->randomElement(['Blanc', 'Noir', 'Gris', 'Argent', 'Beige', 'Rouge', 'Bleu']),
            'condition' => $this->faker->randomElement(['used', 'used', 'used', 'imported']),
            'description_fr' => sprintf(
                '%s %s %d en très bon état. %s, %s. Première main, entretien régulier. Documents en règle.',
                $brand->name,
                $model->name,
                $year,
                $transmission === 'manual' ? 'Boîte manuelle' : 'Boîte automatique',
                $fuel === 'diesel' ? 'diesel' : ($fuel === 'hybrid' ? 'hybride' : 'essence'),
            ),
            'description_ar' => null,
            'city_id' => $city?->id,
            'specs' => [
                'options' => $this->faker->randomElements(
                    ['climatisation', 'jantes_alu', 'gps', 'bluetooth', 'camera_recul', 'sieges_cuir', 'toit_ouvrant', 'abs', 'airbag', 'regulateur'],
                    $this->faker->numberBetween(3, 7)
                ),
                'doors' => $this->faker->randomElement([3, 5]),
                'seats' => $this->faker->randomElement([5, 5, 7]),
            ],
            'status' => VehicleStatus::ACTIVE,
            'views_count' => $this->faker->numberBetween(10, 2000),
            'contacts_count' => $this->faker->numberBetween(0, 80),
            'published_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'expires_at' => now()->addMonths(2),
        ];
    }

    public function configure(): self
    {
        return $this->afterCreating(function (Vehicle $vehicle): void {
            $lat = 18.0735 + $this->faker->randomFloat(4, -0.08, 0.08);
            $lng = -15.9785 + $this->faker->randomFloat(4, -0.08, 0.08);

            DB::statement(
                'UPDATE vehicles SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                [$lng, $lat, $vehicle->id]
            );
        });
    }
}
EOF

echo "==> 4. DemoVehicleSeeder"
cat > database/seeders/DemoVehicleSeeder.php << 'EOF'
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class DemoVehicleSeeder extends Seeder
{
    public function run(): void
    {
        $agencies = Agency::factory()->count(6)->create();

        $totalVehicles = 0;
        foreach ($agencies as $agency) {
            $count = random_int(5, 9);
            Vehicle::factory()->count($count)->create([
                'agency_id' => $agency->id,
                'user_id' => $agency->user_id,
            ]);
            $totalVehicles += $count;
        }

        $this->command->info(sprintf(
            '  Created %d agencies and %d vehicles',
            $agencies->count(),
            $totalVehicles
        ));
    }
}
EOF

echo "==> 5. DatabaseSeeder (ajoute DemoVehicleSeeder)"
cat > database/seeders/DatabaseSeeder.php << 'EOF'
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CitySeeder::class,
            BrandSeeder::class,
            VehicleModelSeeder::class,
            AdminUserSeeder::class,
            DemoVehicleSeeder::class,
        ]);
    }
}
EOF

echo "==> 6. VehicleListResource (listing léger)"
mkdir -p app/Http/Resources/V1
cat > app/Http/Resources/V1/VehicleListResource.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
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
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city?->id,
                'name_fr' => $this->city?->name_fr,
                'name_ar' => $this->city?->name_ar,
            ]),
            'distance_km' => $this->when(
                isset($this->distance_km),
                fn () => round((float) $this->distance_km, 2)
            ),
            'cover_image' => $this->whenLoaded('coverMedia', fn () => $this->coverMedia?->url_webp_md),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
EOF

echo "==> 7. VehicleResource (détail complet)"
cat > app/Http/Resources/V1/VehicleResource.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
                'slug' => $this->brand->slug,
                'logo_url' => $this->brand->logo_url,
            ]),
            'model' => $this->whenLoaded('vehicleModel', fn () => [
                'id' => $this->vehicleModel->id,
                'name' => $this->vehicleModel->name,
                'slug' => $this->vehicleModel->slug,
            ]),
            'year' => $this->year,
            'mileage_km' => $this->mileage_km,
            'price_mru' => $this->price_mru,
            'price_negotiable' => $this->price_negotiable,
            'currency' => $this->currency,
            'fuel' => $this->fuel,
            'transmission' => $this->transmission,
            'body_type' => $this->body_type,
            'color' => $this->color,
            'condition' => $this->condition,
            'description_fr' => $this->description_fr,
            'description_ar' => $this->description_ar,
            'specs' => $this->specs,
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city?->id,
                'name_fr' => $this->city?->name_fr,
                'name_ar' => $this->city?->name_ar,
                'region' => $this->city?->region,
            ]),
            'agency' => $this->whenLoaded('agency', fn () => [
                'id' => $this->agency?->id,
                'name' => $this->agency?->name,
                'slug' => $this->agency?->slug,
                'logo_url' => $this->agency?->logo_url,
                'phone_whatsapp' => $this->agency?->phone_whatsapp,
                'phone_call' => $this->agency?->phone_call,
                'is_verified' => $this->agency?->isVerified() ?? false,
            ]),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'id' => $m->id,
                'url_thumb' => $m->url_thumb,
                'url_md' => $m->url_webp_md,
                'url_lg' => $m->url_webp_lg,
                'is_cover' => $m->is_cover,
            ])),
            'stats' => [
                'views_count' => $this->views_count,
                'contacts_count' => $this->contacts_count,
            ],
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
EOF

echo "==> 8. FormRequest ListVehiclesRequest"
mkdir -p app/Http/Requests/Vehicles
cat > app/Http/Requests/Vehicles/ListVehiclesRequest.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;

class ListVehiclesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'vehicle_model_id' => ['nullable', 'integer', 'exists:vehicle_models,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'year_min' => ['nullable', 'integer', 'min:1980', 'max:2030'],
            'year_max' => ['nullable', 'integer', 'min:1980', 'max:2030'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'mileage_max' => ['nullable', 'integer', 'min:0'],
            'fuel' => ['nullable', 'in:gasoline,diesel,hybrid,electric,lpg'],
            'transmission' => ['nullable', 'in:manual,automatic'],
            'body_type' => ['nullable', 'in:sedan,suv,pickup,hatchback,van,coupe'],
            'condition' => ['nullable', 'in:new,used,imported'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:500'],
            'sort' => ['nullable', 'in:recent,price_asc,price_desc,year_desc,mileage_asc,distance'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ];
    }
}
EOF

echo "==> 9. VehicleController (public)"
mkdir -p app/Http/Controllers/Api/V1
cat > app/Http/Controllers/Api/V1/VehicleController.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\ListVehiclesRequest;
use App\Http\Resources\V1\VehicleListResource;
use App\Http\Resources\V1\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehicleController extends Controller
{
    public function index(ListVehiclesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = Vehicle::query()
            ->with(['brand:id,name,slug', 'vehicleModel:id,name', 'city:id,name_fr,name_ar', 'coverMedia'])
            ->where('status', VehicleStatus::ACTIVE->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        foreach (['brand_id', 'vehicle_model_id', 'city_id', 'fuel', 'transmission', 'body_type', 'condition'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['year_min'])) {
            $query->where('year', '>=', $filters['year_min']);
        }
        if (isset($filters['year_max'])) {
            $query->where('year', '<=', $filters['year_max']);
        }
        if (isset($filters['price_min'])) {
            $query->where('price_mru', '>=', $filters['price_min']);
        }
        if (isset($filters['price_max'])) {
            $query->where('price_mru', '<=', $filters['price_max']);
        }
        if (isset($filters['mileage_max'])) {
            $query->where('mileage_km', '<=', $filters['mileage_max']);
        }

        $hasGeo = isset($filters['lat'], $filters['lng'], $filters['radius_km']);
        if ($hasGeo) {
            $query->nearby(
                (float) $filters['lat'],
                (float) $filters['lng'],
                ((int) $filters['radius_km']) * 1000
            );
        }

        $sort = $filters['sort'] ?? ($hasGeo ? 'distance' : 'recent');
        if (! $hasGeo) {
            match ($sort) {
                'price_asc' => $query->orderBy('price_mru'),
                'price_desc' => $query->orderByDesc('price_mru'),
                'year_desc' => $query->orderByDesc('year'),
                'mileage_asc' => $query->orderBy('mileage_km'),
                default => $query->orderByDesc('published_at'),
            };
        }

        $perPage = (int) ($filters['per_page'] ?? 20);

        return VehicleListResource::collection($query->paginate($perPage));
    }

    public function show(string $id): VehicleResource|JsonResponse
    {
        $vehicle = Vehicle::query()
            ->with([
                'brand',
                'vehicleModel',
                'city',
                'agency:id,name,slug,logo_url,phone_whatsapp,phone_call,status',
                'media',
            ])
            ->where('status', VehicleStatus::ACTIVE->value)
            ->whereNotNull('published_at')
            ->where('id', $id)
            ->first();

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        Vehicle::where('id', $vehicle->id)->update([
            'views_count' => $vehicle->views_count + 1,
        ]);

        return new VehicleResource($vehicle);
    }

    public function similar(string $id): AnonymousResourceCollection|JsonResponse
    {
        $vehicle = Vehicle::find($id);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        $similar = Vehicle::query()
            ->with(['brand:id,name,slug', 'vehicleModel:id,name', 'city:id,name_fr', 'coverMedia'])
            ->where('status', VehicleStatus::ACTIVE->value)
            ->where('id', '!=', $vehicle->id)
            ->where('brand_id', $vehicle->brand_id)
            ->whereBetween('price_mru', [
                (int) ($vehicle->price_mru * 0.7),
                (int) ($vehicle->price_mru * 1.3),
            ])
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        return VehicleListResource::collection($similar);
    }
}
EOF

echo "==> 10. Ajout scope nearby sur Vehicle (idempotent)"
if ! grep -q "scopeNearby" app/Models/Vehicle.php; then
  # Insère scopeNearby juste avant la dernière } du fichier
  python3 - << 'PYEOF'
import re
from pathlib import Path

path = Path('app/Models/Vehicle.php')
content = path.read_text()

scope = '''
    public function scopeNearby(Builder $query, float $lat, float $lng, int $radiusMeters): Builder
    {
        return $query
            ->selectRaw('vehicles.*, ST_Distance(vehicles.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) / 1000 AS distance_km', [$lng, $lat])
            ->whereRaw('ST_DWithin(vehicles.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, $radiusMeters])
            ->orderByRaw('ST_Distance(vehicles.location, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)', [$lng, $lat]);
    }
'''

# Insère avant la dernière accolade fermante de la classe
content = re.sub(r'(\n}\s*)$', scope + r'\1', content, count=1)
path.write_text(content)
print('  scopeNearby ajouté.')
PYEOF
else
  echo "  scopeNearby déjà présent — skip."
fi

echo "==> 11. Routes API (réécriture complète)"
cat > routes/api.php << 'EOF'
<?php

declare(strict_types=1);

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

    // Auth (protégés)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout/all', [AuthController::class, 'logoutAll']);

        Route::get('/me', fn (Request $request) => $request->user()->load('agency'));

        Route::middleware('role.boursa:admin')->prefix('admin')->group(function () {
            Route::get('/dashboard', fn (Request $request) => response()->json([
                'message' => 'Welcome admin',
                'permissions' => $request->user()->getAllPermissions()->pluck('name'),
            ]));
        });

        Route::middleware('role.boursa:agency')->prefix('agency')->group(function () {
            Route::get('/dashboard', fn () => response()->json([
                'message' => 'Welcome agency',
            ]));
        });
    });
});
EOF

echo "==> ✅ Tous les fichiers générés"
echo ""
echo "Prochaines commandes à lancer manuellement :"
echo "  php artisan migrate:fresh --seed"
echo "  php artisan route:list --path=api/v1/vehicles"
echo "  php artisan serve"
