<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Tabla cache ---
        // Generada automáticamente por Laravel para almacenar caché en base de datos
        // La caché guarda resultados de consultas o datos temporales para mejorar
        // el rendimiento evitando consultas repetidas a la base de datos
        Schema::create('cache', function (Blueprint $table) {
            // Clave única que identifica el dato cacheado (ej: "cartas_todas")
            $table->string('key')->primary();

            // Valor almacenado en caché, serializado
            $table->mediumText('value');

            // Timestamp de expiración del dato cacheado
            // Cuando expira, Laravel lo elimina automáticamente
            $table->integer('expiration')->index();
        });

        // --- Tabla cache_locks ---
        // Evita que dos procesos modifiquen el mismo dato en caché simultáneamente
        // Funciona como un semáforo: solo un proceso puede tener el lock a la vez
        Schema::create('cache_locks', function (Blueprint $table) {
            // Clave que identifica el lock (igual que en cache)
            $table->string('key')->primary();

            // Propietario del lock (proceso que lo tiene reservado)
            $table->string('owner');

            // Timestamp de expiración del lock
            // Si expira sin liberarse, otro proceso puede tomarlo
            $table->integer('expiration')->index();
        });
    }

    public function down(): void
    {
        // Se eliminan las tablas si se revierte la migración
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};