<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OtpVerification;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class FirebaseAuthController extends Controller
{
    public function __construct(private readonly Auth $auth) {}

    /**
     * Vérifie un Firebase ID Token et authentifie l'utilisateur
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'firebase_token' => ['required', 'string'],
        ]);

        try {
            // Vérifier le token Firebase
            $verifiedToken = $this->auth->verifyIdToken($request->firebase_token);
        } catch (FailedToVerifyToken $e) {
            return response()->json([
                'message' => 'Token Firebase invalide.',
            ], 401);
        }

        $uid        = $verifiedToken->claims()->get('sub');
        $phone      = $verifiedToken->claims()->get('phone_number');

        if (! $phone) {
            return response()->json([
                'message' => 'Numéro de téléphone manquant dans le token.',
            ], 422);
        }

        // Trouver ou créer l'utilisateur
        $user = User::firstOrCreate(
            ['phone' => $phone],
            [
                'id'                => Str::uuid(),
                'name'              => 'Utilisateur',
                'email'             => null,
                'password'          => null,
                'role'              => UserRole::USER,
                'email_verified_at' => now(),
            ]
        );

        // Enregistrer la vérification OTP
        OtpVerification::create([
            'id'          => Str::uuid(),
            'phone'       => $phone,
            'firebase_uid'=> $uid,
            'status'      => 'verified',
            'verified_at' => now(),
        ]);

        // Générer token Sanctum
        $token = $user->createToken('boursa-web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->id,
                'name'  => $user->name,
                'phone' => $user->phone,
                'role'  => $user->role->value,
            ],
        ]);
    }
}
