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
