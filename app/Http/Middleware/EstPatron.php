<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstPatron
{
    // Bloque l'acces si l'utilisateur connecte n'est pas patron.
    // A utiliser sur les routes reservees au patron (ex : gestion des produits, caisse).
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->estPatron()) {
            abort(403, 'Acces reserve au patron.');
        }

        return $next($request);
    }
}
