<?php

declare(strict_types=1);

namespace App\Services\Sms;

final class SmsManager
{
    public function __construct(private readonly SmsDriver $driver) {}

    public function send(string $phone, string $message): bool
    {
        return $this->driver->send($phone, $message);
    }
}
