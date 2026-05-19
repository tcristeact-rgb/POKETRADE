<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Tabla jobs ---
        // Generada automáticamente por Laravel para el sistema de colas (Queue)
        // Las colas permiten ejecutar tareas en segundo plano (ej: enviar emails)
        // En PokeTrade no las usamos actualmente pero Laravel las necesita
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();

            // Nombre de la cola a la que pertenece el job (ej: "default", "emails")
            $table->string('queue')->index();

            // Datos serializados del job (la tarea a ejecutar)
            $table->longText('payload');

            // Número de intentos realizados para ejecutar el job
            $table->unsignedTinyInteger('attempts');

            // Timestamp de cuando el job fue reservado para ejecutarse
            $table->unsignedInteger('reserved_at')->nullable();

            // Timestamp de cuando el job está disponible para ejecutarse
            $table->unsignedInteger('available_at');

            // Timestamp de creación (no usa timestamps() porque es un entero, no datetime)
            $table->unsignedInteger('created_at');
        });

        // --- Tabla job_batches ---
        // Para agrupar varios jobs en un lote y hacer seguimiento conjunto
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary(); // ID único del lote (string, no autoincremental)
            $table->string('name');          // Nombre descriptivo del lote
            $table->integer('total_jobs');   // Total de jobs en el lote
            $table->integer('pending_jobs'); // Jobs pendientes de ejecutar
            $table->integer('failed_jobs');  // Jobs que han fallado
            $table->longText('failed_job_ids'); // IDs de los jobs fallidos
            $table->mediumText('options')->nullable(); // Opciones adicionales del lote
            $table->integer('cancelled_at')->nullable(); // Timestamp de cancelación
            $table->integer('created_at');   // Timestamp de creación
            $table->integer('finished_at')->nullable(); // Timestamp de finalización
        });

        // --- Tabla failed_jobs ---
        // Almacena los jobs que han fallado para poder reintentar o revisar
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique(); // Identificador único del job fallido
            $table->text('connection');       // Conexión usada (ej: "database", "redis")
            $table->text('queue');            // Cola en la que estaba el job
            $table->longText('payload');      // Datos del job que falló
            $table->longText('exception');    // Error completo que causó el fallo
            $table->timestamp('failed_at')->useCurrent(); // Fecha del fallo
        });
    }

    public function down(): void
    {
        // Se eliminan en orden para respetar dependencias
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};