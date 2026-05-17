<?php

declare(strict_types=1);

namespace App\Http\Middleware\Api;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Authentification requise.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userRole = $user->role instanceof UserRole
            ? $user->role->value
            : (string) $user->role;

        if (! in_array($userRole, $roles, true)) {
            return response()->json([
                'message' => 'Accès refusé. Rôle requis: '.implode(', ', $roles),
            ], Response::HTTP_FORBIDDEN);
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        return $next($request);
    }
}
