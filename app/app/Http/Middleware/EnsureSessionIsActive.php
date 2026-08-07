<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnecte automatiquement un utilisateur authentifié après une période
 * d'inactivité (par défaut 15 minutes), même si le cookie de session est
 * encore valide côté navigateur (ex: session restaurée par Chrome).
 *
 * Utile sur les postes partagés du bloc : si un agent oublie de se
 * déconnecter, la session s'invalide automatiquement au bout de 15 minutes
 * sans clic/requête, et l'utilisateur suivant retombe sur l'écran de
 * connexion avec un message explicite plutôt que sur l'accès de son
 * collègue resté ouvert.
 */
class EnsureSessionIsActive
{
    private const TIMEOUT_MINUTES = 15;

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $lastActivity = $request->session()->get('derniere_activite_le');

            if ($lastActivity && Carbon::parse($lastActivity)->diffInMinutes(now()) >= self::TIMEOUT_MINUTES) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('timeout', 'Session fermée automatiquement après ' . self::TIMEOUT_MINUTES . ' minutes d’inactivité. Merci de vous reconnecter.');
            }

            $request->session()->put('derniere_activite_le', now()->toDateTimeString());
        }

        return $next($request);
    }
}
