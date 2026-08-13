<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de protection par permission spatie.
 *
 * Usage : ->middleware('permission:create modules')
 * Supporte la compatibilité legacy : si l'utilisateur a un rôle
 * super_admin/sous_admin dans le champ legacy, il est considéré comme ayant
 * la permission 'manage roles' uniquement pour super_admin.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'Accès non autorisé.');
        }

        if (!method_exists($user, 'hasAnyPermission')) {
            abort(403, 'Système de permissions non configuré.');
        }

        if ($user->hasAnyPermission($permissions)) {
            return $next($request);
        }

        // Compatibilité legacy : super_admin a implicitement toutes les permissions
        if (property_exists($user, 'role') && $user->role === 'super_admin') {
            return $next($request);
        }

        abort(403, 'Vous ne disposez pas des permissions requises.');
    }
}
