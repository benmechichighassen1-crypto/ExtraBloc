<?php

namespace App\Http\Middleware;

use App\Support\AccessControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accès à l'écran "Contrôle direction" : Direction (droits complets :
 * valider/rejeter/dévalider) ET RH (lecture seule, déclarations validées
 * uniquement, export Excel). Le niveau de droit exact est déterminé dans
 * DirectionController/la vue via AccessControl::hasDirectionAccess() —
 * un seul écran, pas une interface dédiée par profil.
 */
class EnsureDirectionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $username = $user?->getAuthIdentifier();

        if (! AccessControl::hasDirectionAccess($username) && ! AccessControl::hasRhAccess($username)) {
            abort(403, 'Accès réservé à la direction ou au service RH.');
        }

        return $next($request);
    }
}
