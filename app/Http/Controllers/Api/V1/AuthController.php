<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\LoginAction;
use App\Actions\Auth\RegisterAction;
use App\Actions\Auth\SendOtpAction;
use App\Actions\Auth\VerifyOtpAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function sendOtp(SendOtpRequest $request, SendOtpAction $action): JsonResponse
    {
        $phone = $request->normalizedPhone();

        if (! $phone) {
            return response()->json([
                'message' => 'Numéro de téléphone invalide. Format attendu : Mauritanie (+222 XX XX XX XX).',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $action->execute($phone, $request->ip());
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        return response()->json([
            'message' => 'Code envoyé.',
            'phone' => $phone,
            'expires_in_seconds' => 300,
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request, VerifyOtpAction $action): JsonResponse
    {
        $phone = $request->normalizedPhone();

        if (! $phone) {
            return response()->json([
                'message' => 'Numéro de téléphone invalide.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $action->execute(
                $phone,
                (string) $request->input('code'),
                (string) ($request->input('device_name') ?? 'mobile'),
                $request->input('name')
            );
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'token' => $result['token'],
            'user' => $result['user'],
            'is_new' => $result['is_new'],
        ]);
    }

    public function register(RegisterRequest $request, RegisterAction $action): JsonResponse
    {
        $phone = $request->normalizedPhone();

        if (! $phone) {
            return response()->json([
                'message' => 'Numéro de téléphone invalide.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $result = $action->execute([
                'name' => (string) $request->input('name'),
                'phone' => $phone,
                'email' => $request->input('email'),
                'password' => (string) $request->input('password'),
                'language' => (string) ($request->input('language') ?? 'fr'),
            ], (string) ($request->input('device_name') ?? 'mobile'));
        } catch (DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'token'                   => $result['token'],
            'user'                    => $result['user'],
            'email_verification_sent' => $result['email_verification_sent'] ?? false,
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        try {
            $result = $action->execute(
                (string) $request->input('identifier'),
                (string) $request->input('password'),
                (string) ($request->input('device_name') ?? 'mobile')
            );
        } catch (DomainException $e) {
            $status = str_contains($e->getMessage(), 'tentatives')
                ? Response::HTTP_TOO_MANY_REQUESTS
                : Response::HTTP_UNAUTHORIZED;

            return response()->json([
                'message' => $e->getMessage(),
            ], $status);
        }

        return response()->json([
            'token' => $result['token'],
            'user' => $result['user'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Toutes les sessions ont été révoquées.']);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $user->update($validated);

        return response()->json([
            'message' => 'Profil mis à jour',
            'user' => $user->fresh()->load('agency'),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Si l'utilisateur a déjà un password, vérifier l'ancien
        if ($user->password) {
            if (! isset($data['current_password']) || ! Hash::check($data['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'Mot de passe actuel incorrect.',
                    'errors' => ['current_password' => ['validation.password.current']],
                ], 422);
            }
        }

        $user->update(['password' => Hash::make($data['new_password'])]);

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }
}
