<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Helpers\PhoneNormalizer;
use DomainException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

final class LoginAction
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    /**
     * @return array{user: User, token: string}
     */
    public function execute(string $identifier, string $password, string $deviceName = 'mobile'): array
    {
        $rateKey = 'login:'.mb_strtolower($identifier);

        if (RateLimiter::tooManyAttempts($rateKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($rateKey);

            throw new DomainException(
                sprintf('Trop de tentatives. Réessayez dans %d secondes.', $seconds)
            );
        }

        $user = $this->resolveUser($identifier);

        if (! $user || ! $user->password || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($rateKey, self::DECAY_SECONDS);

            throw new DomainException('Identifiants invalides.');
        }

        RateLimiter::clear($rateKey);

        // Bloquer si email non vérifié
        if (str_contains($identifier, '@') && ! $user->hasVerifiedEmail()) {
            throw new DomainException('Veuillez vérifier votre adresse email avant de vous connecter.');
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user->load('agency'),
            'token' => $token,
        ];
    }

    private function resolveUser(string $identifier): ?User
    {
        if (str_contains($identifier, '@')) {
            return User::where('email', mb_strtolower($identifier))->first();
        }

        $phone = PhoneNormalizer::normalize($identifier);

        return $phone ? User::where('phone', $phone)->first() : null;
    }
}
