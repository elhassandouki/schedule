<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureRole {
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403, 'Accès non autorisé.');
        }

        // Compatibilité : le rôle peut être dans le champ legacy `role`
        // (attribut Eloquent, pas propriété PHP) ou assigné via spatie.
        $legacyRole = null;
        if (is_object($user) && method_exists($user, 'getAttribute') && array_key_exists('role', $user->getAttributes())) {
            $legacyRole = $user->getAttribute('role');
        }

        if ($legacyRole !== null && in_array($legacyRole, $roles, true)) {
            return $next($request);
        }

        if (method_exists($user, 'roles') && $user->getRoleNames()->intersect($roles)->isNotEmpty()) {
            return $next($request);
        }

        abort(403, 'Accès non autorisé pour ce rôle.');
    }
}
