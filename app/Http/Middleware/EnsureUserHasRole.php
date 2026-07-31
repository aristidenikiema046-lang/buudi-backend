<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Vérifie que l'utilisateur authentifié possède l'un des rôles autorisés.
     * Usage dans les routes : ->middleware('role:client') ou ->middleware('role:client,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (!$user || !in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => "Accès refusé : rôle non autorisé.",
            ], 403);
        }

        return $next($request);
    }
}
