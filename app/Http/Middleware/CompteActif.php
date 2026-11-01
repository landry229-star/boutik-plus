<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->actif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Ce compte est désactivé. Contactez le patron de votre boutique.',
            ]);
        }

        return $next($request);
    }
}
