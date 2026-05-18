#!/usr/bin/env bash
# Boursa — Lead tracking + Meta Conversions API
#
# Usage depuis ~/projects/boursa/boursa-api :
#   bash scripts/create_meta_lead_tracking.sh

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "==> 1. Enum MetaEventType"
cat > app/Enums/MetaEventType.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Enums;

enum MetaEventType: string
{
    case VIEW_CONTENT = 'ViewContent';
    case SEARCH = 'Search';
    case LEAD = 'Lead';
    case ADD_TO_WISHLIST = 'AddToWishlist';
    case COMPLETE_REGISTRATION = 'CompleteRegistration';
    case LOGIN = 'Login';
    case SUBMIT_APPLICATION = 'SubmitApplication';
}
EOF

echo "==> 2. Service MetaEventBuilder (build payloads)"
mkdir -p app/Services/Meta
cat > app/Services/Meta/MetaEventBuilder.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Enums\MetaEventType;
use App\Models\User;
use Illuminate\Http\Request;

final class MetaEventBuilder
{
    public function build(
        MetaEventType $eventType,
        ?Request $request = null,
        ?User $user = null,
        array $customData = [],
        ?string $eventId = null,
        ?string $sourceUrl = null,
    ): array {
        return [
            'event_name' => $eventType->value,
            'event_time' => time(),
            'event_id' => $eventId ?? $this->generateEventId($eventType),
            'event_source_url' => $sourceUrl,
            'action_source' => $request?->header('X-Source') === 'app' ? 'app' : 'website',
            'user_data' => $this->buildUserData($user, $request),
            'custom_data' => $customData,
        ];
    }

    private function buildUserData(?User $user, ?Request $request): array
    {
        $data = [];

        if ($user) {
            $data['external_id'] = [$this->hash($user->id)];

            if ($user->email) {
                $data['em'] = [$this->hash(mb_strtolower(trim($user->email)))];
            }

            if ($user->phone) {
                $data['ph'] = [$this->hash($user->phone)];
            }
        }

        if ($request) {
            if ($ip = $request->ip()) {
                $data['client_ip_address'] = $ip;
            }

            if ($ua = $request->userAgent()) {
                $data['client_user_agent'] = $ua;
            }

            if ($fbp = $request->cookie('_fbp')) {
                $data['fbp'] = $fbp;
            }

            if ($fbc = $request->cookie('_fbc')) {
                $data['fbc'] = $fbc;
            } elseif ($fbclid = $request->query('fbclid')) {
                $data['fbc'] = sprintf('fb.1.%d.%s', time() * 1000, $fbclid);
            }
        }

        return $data;
    }

    private function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    private function generateEventId(MetaEventType $eventType): string
    {
        return strtolower($eventType->value).'_'.bin2hex(random_bytes(8)).'_'.time();
    }
}
EOF

echo "==> 3. Service MetaCapiService (client HTTP Meta)"
cat > app/Services/Meta/MetaCapiService.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Services\Meta;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MetaCapiService
{
    private const API_VERSION = 'v21.0';

    public function send(array $eventPayload): bool
    {
        $pixelId = (string) config('services.meta.pixel_id', '');
        $accessToken = (string) config('services.meta.capi_access_token', '');
        $testEventCode = (string) config('services.meta.capi_test_event_code', '');

        // Pas de credentials → log-only mode (dev)
        if ($pixelId === '' || $accessToken === '') {
            Log::channel('single')->info('[Meta CAPI:no-op]', [
                'reason' => 'Missing META_PIXEL_ID or META_CAPI_ACCESS_TOKEN',
                'event' => $eventPayload,
            ]);

            return true;
        }

        $payload = ['data' => [$eventPayload]];

        if ($testEventCode !== '') {
            $payload['test_event_code'] = $testEventCode;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->post(
                    sprintf('https://graph.facebook.com/%s/%s/events', self::API_VERSION, $pixelId),
                    array_merge($payload, ['access_token' => $accessToken])
                );

            if ($response->failed()) {
                Log::channel('single')->error('[Meta CAPI:fail]', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'event_name' => $eventPayload['event_name'] ?? 'unknown',
                    'event_id' => $eventPayload['event_id'] ?? null,
                ]);

                return false;
            }

            Log::channel('single')->info('[Meta CAPI:sent]', [
                'event_name' => $eventPayload['event_name'] ?? 'unknown',
                'event_id' => $eventPayload['event_id'] ?? null,
                'events_received' => $response->json('events_received'),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::channel('single')->error('[Meta CAPI:exception]', [
                'message' => $e->getMessage(),
                'event_name' => $eventPayload['event_name'] ?? 'unknown',
            ]);

            return false;
        }
    }
}
EOF

echo "==> 4. Job SendMetaCapiEventJob (async)"
mkdir -p app/Jobs/Meta
cat > app/Jobs/Meta/SendMetaCapiEventJob.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Jobs\Meta;

use App\Services\Meta\MetaCapiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendMetaCapiEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 20;

    public function __construct(public readonly array $eventPayload) {}

    public function handle(MetaCapiService $service): void
    {
        $service->send($this->eventPayload);
    }

    public function failed(Throwable $e): void
    {
        Log::channel('single')->error('[Meta CAPI Job failed]', [
            'message' => $e->getMessage(),
            'event_name' => $this->eventPayload['event_name'] ?? 'unknown',
        ]);
    }
}
EOF

echo "==> 5. Config services.meta"
python3 - << 'PYEOF'
from pathlib import Path
import re

path = Path('config/services.php')
content = path.read_text()

if "'meta'" in content:
    print("  → services.meta déjà présent.")
else:
    block = """
    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'pixel_id' => env('META_PIXEL_ID'),
        'capi_access_token' => env('META_CAPI_ACCESS_TOKEN'),
        'capi_test_event_code' => env('META_CAPI_TEST_EVENT_CODE'),
    ],
"""
    # Insérer avant le ];  final
    content = re.sub(r'(\n\];?\s*)$', block + r'\1', content, count=1)
    path.write_text(content)
    print("  → services.meta ajouté.")
PYEOF

echo "==> 6. Action SubmitLeadAction"
mkdir -p app/Actions/Leads
cat > app/Actions/Leads/SubmitLeadAction.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Enums\LeadType;
use App\Enums\MetaEventType;
use App\Jobs\Meta\SendMetaCapiEventJob;
use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Meta\MetaEventBuilder;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SubmitLeadAction
{
    public function __construct(private readonly MetaEventBuilder $builder) {}

    public function execute(
        Vehicle $vehicle,
        LeadType $type,
        Request $request,
        ?User $user = null,
        ?string $message = null,
        ?string $senderName = null,
        ?string $senderPhone = null,
        ?string $eventId = null,
    ): Lead {
        if ($vehicle->status?->value !== 'active') {
            throw new DomainException('Ce véhicule n\'est plus disponible.');
        }

        return DB::transaction(function () use (
            $vehicle, $type, $request, $user, $message, $senderName, $senderPhone, $eventId
        ): Lead {
            $lead = Lead::create([
                'vehicle_id' => $vehicle->id,
                'agency_id' => $vehicle->agency_id,
                'sender_user_id' => $user?->id,
                'sender_name' => $senderName ?? $user?->name,
                'sender_phone' => $senderPhone ?? $user?->phone,
                'type' => $type,
                'message' => $message,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Vehicle::where('id', $vehicle->id)->increment('contacts_count');

            AnalyticsEvent::create([
                'user_id' => $user?->id,
                'vehicle_id' => $vehicle->id,
                'event_type' => 'lead',
                'metadata' => [
                    'lead_id' => $lead->id,
                    'lead_type' => $type->value,
                    'agency_id' => $vehicle->agency_id,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'fbclid' => $request->query('fbclid'),
                'utm_source' => $request->query('utm_source'),
                'utm_campaign' => $request->query('utm_campaign'),
            ]);

            $payload = $this->builder->build(
                eventType: MetaEventType::LEAD,
                request: $request,
                user: $user,
                customData: [
                    'content_type' => 'vehicle',
                    'content_ids' => [$vehicle->id],
                    'content_name' => trim(sprintf(
                        '%s %s %d',
                        $vehicle->brand?->name ?? '',
                        $vehicle->vehicleModel?->name ?? '',
                        $vehicle->year
                    )),
                    'content_category' => $vehicle->body_type ?? 'vehicle',
                    'currency' => $vehicle->currency,
                    'value' => $vehicle->price_mru,
                    'lead_type' => $type->value,
                    'agency_id' => $vehicle->agency_id,
                ],
                eventId: $eventId ?? 'lead_'.$lead->id,
                sourceUrl: $request->fullUrl(),
            );

            SendMetaCapiEventJob::dispatch($payload);

            return $lead->fresh();
        });
    }
}
EOF

echo "==> 7. Action TrackViewContentAction"
cat > app/Actions/Leads/TrackViewContentAction.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\Enums\MetaEventType;
use App\Jobs\Meta\SendMetaCapiEventJob;
use App\Models\AnalyticsEvent;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Meta\MetaEventBuilder;
use Illuminate\Http\Request;

final class TrackViewContentAction
{
    public function __construct(private readonly MetaEventBuilder $builder) {}

    public function execute(
        Vehicle $vehicle,
        Request $request,
        ?User $user = null,
        ?string $eventId = null,
    ): void {
        AnalyticsEvent::create([
            'user_id' => $user?->id,
            'vehicle_id' => $vehicle->id,
            'event_type' => 'view_content',
            'metadata' => [
                'agency_id' => $vehicle->agency_id,
                'price_mru' => $vehicle->price_mru,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'fbclid' => $request->query('fbclid'),
            'utm_source' => $request->query('utm_source'),
            'utm_campaign' => $request->query('utm_campaign'),
        ]);

        $payload = $this->builder->build(
            eventType: MetaEventType::VIEW_CONTENT,
            request: $request,
            user: $user,
            customData: [
                'content_type' => 'vehicle',
                'content_ids' => [$vehicle->id],
                'content_name' => trim(sprintf(
                    '%s %s %d',
                    $vehicle->brand?->name ?? '',
                    $vehicle->vehicleModel?->name ?? '',
                    $vehicle->year
                )),
                'content_category' => $vehicle->body_type ?? 'vehicle',
                'currency' => $vehicle->currency,
                'value' => $vehicle->price_mru,
            ],
            eventId: $eventId ?? 'view_'.$vehicle->id.'_'.($user?->id ?? $request->ip()).'_'.time(),
            sourceUrl: $request->fullUrl(),
        );

        SendMetaCapiEventJob::dispatch($payload);
    }
}
EOF

echo "==> 8. FormRequest SubmitLeadRequest"
mkdir -p app/Http/Requests/Leads
cat > app/Http/Requests/Leads/SubmitLeadRequest.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Support\Helpers\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class SubmitLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:message,call_click,whatsapp_click'],
            'message' => ['nullable', 'string', 'max:2000', 'required_if:type,message'],
            'sender_name' => ['nullable', 'string', 'max:150'],
            'sender_phone' => ['nullable', 'string', 'max:30'],
            'event_id' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function normalizedPhone(): ?string
    {
        $phone = $this->input('sender_phone');

        return is_string($phone) && $phone !== ''
            ? PhoneNormalizer::normalize($phone)
            : null;
    }
}
EOF

echo "==> 9. Controller LeadController (public)"
cat > app/Http/Controllers/Api/V1/LeadController.php << 'EOF'
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
EOF

echo "==> 10. Controller AnalyticsController (track ViewContent + custom)"
cat > app/Http/Controllers/Api/V1/AnalyticsController.php << 'EOF'
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Leads\TrackViewContentAction;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AnalyticsController extends Controller
{
    public function trackView(Request $request, string $vehicleId, TrackViewContentAction $action): JsonResponse
    {
        $vehicle = Vehicle::with(['brand', 'vehicleModel'])
            ->where('status', VehicleStatus::ACTIVE->value)
            ->find($vehicleId);

        if (! $vehicle) {
            return response()->json(['message' => 'Véhicule introuvable.'], 404);
        }

        // Limite: 1 ViewContent par IP+vehicle / 5 minutes (dedup naturel)
        $rateKey = sprintf('view:%s:%s', $request->ip(), $vehicleId);
        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            return response()->json(['tracked' => false, 'reason' => 'recent_view']);
        }
        RateLimiter::hit($rateKey, 300);

        $action->execute(
            vehicle: $vehicle,
            request: $request,
            user: $request->user(),
            eventId: $request->input('event_id'),
        );

        return response()->json(['tracked' => true]);
    }
}
EOF

echo "==> 11. Routes API (ajout des endpoints lead + analytics)"
python3 - << 'PYEOF'
from pathlib import Path

path = Path('routes/api.php')
content = path.read_text()

# Vérifier idempotence
if 'LeadController' in content:
    print("  → Routes lead déjà présentes.")
else:
    # Imports
    if 'use App\\Http\\Controllers\\Api\\V1\\VehicleController;' in content:
        content = content.replace(
            'use App\\Http\\Controllers\\Api\\V1\\VehicleController;',
            'use App\\Http\\Controllers\\Api\\V1\\AnalyticsController;\nuse App\\Http\\Controllers\\Api\\V1\\LeadController;\nuse App\\Http\\Controllers\\Api\\V1\\VehicleController;'
        )

    # Ajouter route lead + track-view dans le bloc vehicles public
    old = "Route::get('/{id}/similar', [VehicleController::class, 'similar'])\n            ->where('id', '[0-9a-fA-F\\-]{36}');\n    });"
    new = """Route::get('/{id}/similar', [VehicleController::class, 'similar'])
            ->where('id', '[0-9a-fA-F\\-]{36}');

        Route::post('/{id}/lead', [LeadController::class, 'store'])
            ->where('id', '[0-9a-fA-F\\-]{36}');

        Route::post('/{id}/track-view', [AnalyticsController::class, 'trackView'])
            ->where('id', '[0-9a-fA-F\\-]{36}');
    });"""

    content = content.replace(old, new)
    path.write_text(content)
    print("  → Routes lead + track-view ajoutées.")
PYEOF

echo "==> 12. .env.example (ajout vars Meta si manquantes)"
if ! grep -q "META_PIXEL_ID" .env.example 2>/dev/null; then
  cat >> .env.example << 'EOF'

# Meta / Facebook Conversions API
META_APP_ID=
META_APP_SECRET=
META_PIXEL_ID=
META_CAPI_ACCESS_TOKEN=
META_CAPI_TEST_EVENT_CODE=
EOF
  echo "  → Variables META ajoutées dans .env.example"
else
  echo "  → Variables META déjà dans .env.example"
fi

echo ""
echo "==> ✅ Lead tracking + Meta CAPI installés"
echo ""
echo "Prochaines commandes :"
echo "  php artisan optimize:clear"
echo "  php artisan route:list --path=api/v1/vehicles | grep -E 'lead|track'"
echo "  (le script de test arrive ensuite)"
