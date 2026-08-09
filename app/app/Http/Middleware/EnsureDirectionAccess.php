<?php

namespace App\Http\Middleware;

use App\Support\AccessControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDirectionAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! AccessControl::hasDirectionAccess($user?->getAuthIdentifier())) {
            abort(403, 'Accès réservé à la direction.');
        }

        return $next($request);
    }
}
