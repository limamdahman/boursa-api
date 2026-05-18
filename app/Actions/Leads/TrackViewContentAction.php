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
