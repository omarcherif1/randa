<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Règle 3.2 — changement de mot de passe forcé au premier login / après un reset.
 * Tant que `must_change_password` est vrai, toute navigation est redirigée vers
 * l'écran de changement (sauf cet écran lui-même et la déconnexion).
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->must_change_password
            && ! $request->routeIs('password.forced', 'password.forced.update', 'logout')
        ) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Changement de mot de passe requis.'], 423);
            }

            return redirect()->route('password.forced');
        }

        return $next($request);
    }
}
