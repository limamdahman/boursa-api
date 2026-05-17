<?php

declare(strict_types=1);

namespace App\Services\Sms;

interface SmsDriver
{
    /**
     * Envoie un SMS au numéro donné. Retourne true si accepté par le provider.
     */
    public function send(string $phone, string $message): bool;
}
