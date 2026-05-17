<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\OtpCode;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

final class SendOtpAction
{
    private const CODE_TTL_SECONDS = 300;        // 5 minutes

    private const MAX_SENDS_PER_WINDOW = 3;

    private const RATE_WINDOW_SECONDS = 900;     // 15 minutes

    public function __construct(private readonly SmsManager $sms) {}

    /**
     * Envoie un code OTP au téléphone donné.
     *
     * @throws RuntimeException si rate-limit atteint
     */
    public function execute(string $phoneE164, ?string $ip = null): void
    {
        $rateKey = 'otp_send:'.$phoneE164;

        if (RateLimiter::tooManyAttempts($rateKey, self::MAX_SENDS_PER_WINDOW)) {
            $seconds = RateLimiter::availableIn($rateKey);

            throw new RuntimeException(
                sprintf('Trop de tentatives. Réessayez dans %d secondes.', $seconds)
            );
        }

        RateLimiter::hit($rateKey, self::RATE_WINDOW_SECONDS);

        // Invalide les anciens codes non consommés du même numéro
        OtpCode::where('phone', $phoneE164)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = (string) random_int(100000, 999999);

        OtpCode::create([
            'phone' => $phoneE164,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addSeconds(self::CODE_TTL_SECONDS),
            'ip_address' => $ip,
        ]);

        $this->sms->send(
            $phoneE164,
            sprintf('Boursa: votre code de vérification est %s. Valide 5 minutes.', $code)
        );
    }
}
