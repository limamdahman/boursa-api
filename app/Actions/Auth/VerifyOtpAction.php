<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\OtpCode;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class VerifyOtpAction
{
    private const MAX_ATTEMPTS = 3;

    /**
     * Vérifie le code OTP, crée l'utilisateur si nouveau, retourne le token.
     *
     * @return array{user: User, token: string, is_new: bool}
     */
    public function execute(string $phoneE164, string $code, string $deviceName = 'mobile', ?string $name = null): array
    {
        return DB::transaction(function () use ($phoneE164, $code, $deviceName, $name): array {
            $otp = OtpCode::where('phone', $phoneE164)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $otp) {
                throw new DomainException('Code invalide ou expiré.');
            }

            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                $otp->update(['consumed_at' => now()]);

                throw new DomainException('Trop de tentatives. Demandez un nouveau code.');
            }

            if (! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');

                throw new DomainException('Code incorrect.');
            }

            $otp->update(['consumed_at' => now()]);

            $user = User::where('phone', $phoneE164)->first();
            $isNew = false;

            if (! $user) {
                $user = User::create([
                    // Vom Nutzer angegebener Name bei der Registrierung, sonst generischer Name
                    'name' => ($name !== null && trim($name) !== '') ? trim($name) : 'Utilisateur '.substr($phoneE164, -4),
                    'phone' => $phoneE164,
                    'phone_verified_at' => now(),
                    'role' => UserRole::USER,
                    'language' => 'fr',
                ]);

                $user->syncRoles([UserRole::USER->value]);
                $isNew = true;
            } elseif ($user->phone_verified_at === null) {
                $user->update(['phone_verified_at' => now()]);
            }

            $token = $user->createToken($deviceName)->plainTextToken;

            return [
                'user' => $user->fresh()->load('agency'),
                'token' => $token,
                'is_new' => $isNew,
            ];
        });
    }
}
