<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // En Render (y en general detrás de un proxy/load balancer) la app
        // recibe las peticiones a través del proxy, no del cliente directo.
        // Confiar en el proxy permite a Laravel detectar correctamente el
        // esquema HTTPS y la IP real del cliente desde las cabeceras X-Forwarded-*.
        $middleware->trustProxies(at: '*');

        // Alias del middleware de administrador, usado en routes/api.php
        $middleware->alias([
            'es.admin' => \App\Http\Middleware\EsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
