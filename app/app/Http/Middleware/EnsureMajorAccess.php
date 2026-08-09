<?php

namespace App\Http\Middleware;

use App\Support\AccessControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accès à l'écran de pré-validation : réservé aux majors du bloc
 * (app.major_users) et, par cohérence hiérarchique, à la Direction
 * (app.direction_users) qui peut prévalider si besoin.
 */
class EnsureMajorAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! AccessControl::hasMajorAccess($user?->getAuthIdentifier())) {
            abort(403, 'Accès réservé aux majors du bloc.');
        }

        return $next($request);
    }
}
