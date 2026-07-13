<?php

use Illuminate\Support\Facades\Route;

// Route::view en vez de una closure: una ruta con closure NO se puede cachear
// (Laravel no sabe serializarla), y `php artisan route:cache` falla entero por
// esta sola ruta — que además solo sirve la página de bienvenida de Laravel.
Route::view('/', 'welcome');
