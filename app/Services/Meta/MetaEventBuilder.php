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
