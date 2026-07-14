<?php

use App\Http\Controllers\IndiceController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',

        // La raíz devuelve un índice de endpoints (antes: la página de bienvenida
        // de Laravel). Se registra aquí, en `then`, y NO en un routes/web.php:
        //
        // el grupo de middleware "web" arranca una SESIÓN y monta CSRF, y esto es
        // una API sin estado que autentica con un JWT en la cabecera. No hay tabla
        // `sessions` —ninguna migración la crea— así que una sola ruta en ese grupo
        // revienta con "no such table: sessions" en cuanto el driver de sesión no
        // sea 'array'. Era una mina esperando a que alguien tocara el .env.
        //
        // Sin routes/web.php, la aplicación no tiene grupo web en absoluto. Que es
        // lo correcto: aquí no hay nada web que servir.
        then: fn () => Route::get('/', IndiceController::class),
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // EstablecerIdioma va en TODAS las rutas de la API y lo antes posible:
        // fija el locale a partir de Accept-Language, y de él dependen tanto
        // __() como los mensajes de validación. Si se ejecutase después de un
        // controlador o de un validador, ya sería tarde.
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\EstablecerIdioma::class,
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
