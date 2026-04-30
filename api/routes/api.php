<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartaController;
use App\Http\Controllers\TradeoController;

// Rutas públicas de autenticación
Route::post('/auth/registro', [AuthController::class, 'registro']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Rutas públicas de cartas y tradeos
Route::get('/cartas', [CartaController::class, 'index']);
Route::get('/cartas/{id}', [CartaController::class, 'show']);
Route::get('/tradeos', [TradeoController::class, 'index']);
Route::get('/tradeos/{id}', [TradeoController::class, 'show']);

// Rutas protegidas con JWT
Route::middleware('auth:api')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/tradeos', [TradeoController::class, 'store']);
    Route::put('/tradeos/{id}', [TradeoController::class, 'update']);
    Route::delete('/tradeos/{id}', [TradeoController::class, 'destroy']);
    Route::get('/mis-tradeos', [TradeoController::class, 'misTradeos']);
});

// Rutas solo para administradores
Route::middleware(['auth:api', \App\Http\Middleware\EsAdmin::class])->group(function () {
    Route::post('/cartas', [CartaController::class, 'store']);
    Route::put('/cartas/{id}', [CartaController::class, 'update']);
    Route::delete('/cartas/{id}', [CartaController::class, 'destroy']);
});