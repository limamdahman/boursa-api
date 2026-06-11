<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    // Appelé depuis le lien email → redirige vers le frontend
    public function verify(Request $request, string $id, string $hash): mixed
    {
        $user = User::findOrFail($id);
        $lang = $user->language ?? 'fr';
        $frontend = config('app.frontend_url', 'http://localhost:4321');

        if (! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect("{$frontend}/{$lang}/connexion?email_error=invalid");
        }

        if (! $request->hasValidSignature()) {
            return redirect("{$frontend}/{$lang}/connexion?email_error=expired");
        }

        if ($user->hasVerifiedEmail()) {
            return redirect("{$frontend}/{$lang}/connexion?email_verified=already");
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect("{$frontend}/{$lang}/connexion?email_verified=1");
    }

    // Renvoyer l'email de vérification
    public function resend(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json(['message' => 'Utilisateur non trouvé.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email déjà vérifié.'], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Email de vérification renvoyé.']);
    }
}
