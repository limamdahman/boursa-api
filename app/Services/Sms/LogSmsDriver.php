<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

final class LogSmsDriver implements SmsDriver
{
    public function send(string $phone, string $message): bool
    {
        Log::channel('single')->info('[SMS:log driver]', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}
