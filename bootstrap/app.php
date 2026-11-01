<?php

use App\Http\Middleware\CompteActif;
use App\Http\Middleware\EstPatron;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        // On enregistre l'alias "est.patron" utilise dans routes/web.php
        $middleware->alias([
            'est.patron' => EstPatron::class,
            'compte.actif' => CompteActif::class,
        ]);
        $middleware->preventRequestForgery(except: [
            'webhooks/fedapay',
            'mobile-money/fedapay/retour/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
