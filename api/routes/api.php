<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartaController;
use App\Http\Controllers\SerieController;
use App\Http\Controllers\SetController;
use App\Http\Controllers\TradeoController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\UsuarioController;

/*
|--------------------------------------------------------------------------
| Rutas públicas — sin autenticación
|--------------------------------------------------------------------------
*/

// Auth
Route::post('/auth/registro', [AuthController::class, 'registro']);
Route::post('/auth/login',    [AuthController::class, 'login']);

// Catálogo — lectura pública
// /cartas/filtros va antes de /cartas/{id} para que "filtros"
// no se interprete como un ID de carta
Route::get('/cartas',         [CartaController::class, 'index']);
Route::get('/cartas/filtros', [CartaController::class, 'filtros']);
Route::get('/cartas/{id}',    [CartaController::class, 'show']);

// Expansiones — índice de series y sets del TCG, lectura pública
// (el índice lo siembra el comando: php artisan tcgdex:sync-sets)
Route::get('/series',      [SerieController::class, 'index']);
Route::get('/series/{id}', [SerieController::class, 'show']);
Route::get('/sets',        [SetController::class, 'index']);
Route::get('/sets/{id}',   [SetController::class, 'show']);

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