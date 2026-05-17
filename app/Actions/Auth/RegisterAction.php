<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\UserRole;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class RegisterAction
{
    /**
     * @param  array{name: string, phone: string, email?: ?string, password: string, language?: string}  $data
     * @return array{user: User, token: string}
     */
    public function execute(array $data, string $deviceName = 'mobile'): array
    {
        return DB::transaction(function () use ($data, $deviceName): array {
            if (User::where('phone', $data['phone'])->exists()) {
                throw new DomainException('Un compte existe déjà avec ce numéro.');
            }

            if (! empty($data['email']) && User::where('email', $data['email'])->exists()) {
                throw new DomainException('Un compte existe déjà avec cet email.');
            }

            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => UserRole::USER,
                'language' => $data['language'] ?? 'fr',
            ]);

            $user->syncRoles([UserRole::USER->value]);

            $token = $user->createToken($deviceName)->plainTextToken;

            return [
                'user' => $user->load('agency'),
                'token' => $token,
            ];
        });
    }
}
