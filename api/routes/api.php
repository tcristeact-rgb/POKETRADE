<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartaController;
use App\Http\Controllers\SerieController;
use App\Http\Controllers\SetController;
use App\Http\Controllers\TradeoController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\SaludController;
use App\Http\Controllers\UsuarioController;

/*
|--------------------------------------------------------------------------
| Rutas públicas — sin autenticación
|--------------------------------------------------------------------------
*/

// Salud del servicio. Sin base de datos, sin TCGdex, sin caché: si esto responde,
// el contenedor está en pie. Sirve para monitorizar y para despertar a Render
// (plan free) por el coste más bajo posible — ver SaludController.
Route::get('/health', SaludController::class);

// Auth
Route::post('/auth/registro', [AuthController::class, 'registro']);
Route::post('/auth/login',    [AuthController::class, 'login']);

// Catálogo — lectura pública
// /cartas/filtros, /cartas/buscar y /cartas/destacadas van antes de
// /cartas/{id} para que no se interpreten como un ID de carta
Route::get('/cartas',            [CartaController::class, 'index']);
Route::get('/cartas/filtros',    [CartaController::class, 'filtros']);
Route::get('/cartas/buscar',     [CartaController::class, 'buscar']);
Route::get('/cartas/destacadas', [CartaController::class, 'destacadas']);
Route::get('/cartas/{id}',       [CartaController::class, 'show']);

// Expansiones — índice de series y sets del TCG, lectura pública
// (el índice lo siembra el comando: php artisan tcgdex:sync-sets)
Route::get('/series',      [SerieController::class, 'index']);
Route::get('/series/{id}', [SerieController::class, 'show']);
Route::get('/sets',        [SetController::class, 'index']);
Route::get('/sets/{id}',   [SetController::class, 'show']);
// La ruta que dispara el cacheo bajo demanda de las cartas del set
Route::get('/sets/{id}/cartas', [SetController::class, 'cartas']);

// Tradeos — lectura pública
Route::get('/tradeos',      [TradeoController::class, 'index']);
Route::get('/tradeos/{id}', [TradeoController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Rutas protegidas — requieren JWT
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Perfil
    Route::get('/usuario/perfil',   [UsuarioController::class, 'perfil']);
    Route::put('/usuario/perfil',   [UsuarioController::class, 'actualizarPerfil']);
    Route::put('/usuario/password', [UsuarioController::class, 'cambiarPassword']);

    // Inventario
    Route::get('/inventario',         [InventarioController::class, 'index']);
    Route::post('/inventario',        [InventarioController::class, 'store']);
    Route::delete('/inventario/{id}', [InventarioController::class, 'destroy']);

    // Tradeos — escritura
    Route::post('/tradeos',              [TradeoController::class, 'store']);
    Route::post('/tradeos/{id}/aceptar', [TradeoController::class, 'aceptar']);
    Route::put('/tradeos/{id}',          [TradeoController::class, 'update']);
    Route::delete('/tradeos/{id}',       [TradeoController::class, 'destroy']);
    Route::get('/mis-tradeos',           [TradeoController::class, 'misTradeos']);

    // Admin
    Route::middleware('es.admin')->group(function () {
        Route::post('/cartas',           [CartaController::class, 'store']);
        Route::put('/cartas/{id}',       [CartaController::class, 'update']);
        Route::delete('/cartas/{id}',    [CartaController::class, 'destroy']);

        Route::get('/admin/usuarios',         [UsuarioController::class, 'index']);
        Route::delete('/admin/usuarios/{id}', [UsuarioController::class, 'destroy']);
    });
});