<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS-Treiber für Infobip (REST API /sms/2/text/advanced).
 */
final class InfobipSmsDriver implements SmsDriver
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $sender,
    ) {}

    public function send(string $phone, string $message): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'App '.$this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(10)->post(
            rtrim($this->baseUrl, '/').'/sms/2/text/advanced',
            [
                'messages' => [
                    [
                        'destinations' => [['to' => ltrim($phone, '+')]],
                        'from' => $this->sender,
                        'text' => $message,
                    ],
                ],
            ]
        );

        if ($response->failed()) {
            Log::channel('single')->error('[SMS:infobip] Versand fehlgeschlagen', [
                'status' => $response->status(),
                'phone_suffix' => substr($phone, -4),
            ]);

            return false;
        }

        $groupId = $response->json('messages.0.status.groupId');

        // groupId 1 = PENDING, 3 = DELIVERED — beide gelten als akzeptiert
        $accepted = in_array($groupId, [1, 3], true);

        if (! $accepted) {
            Log::channel('single')->warning('[SMS:infobip] Nachricht abgelehnt', [
                'group' => $response->json('messages.0.status.groupName'),
                'phone_suffix' => substr($phone, -4),
            ]);
        }

        return $accepted;
    }
}
