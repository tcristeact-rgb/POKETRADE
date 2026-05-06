<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Tabla principal de tradeos ---
        // Cada tradeo pertenece a un usuario y tiene un estado y descripción
        Schema::create('tradeos', function (Blueprint $table) {
            $table->id();

            // Clave foránea al usuario que crea el tradeo
            // onDelete('cascade') → si se borra el usuario, se borran sus tradeos
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Descripción opcional que el usuario escribe para explicar su oferta
            $table->text('descripcion')->nullable();

            // Estado del tradeo: 'activo', 'cerrado' o 'cancelado'
            // Por defecto se crea como activo
            $table->string('estado')->default('activo');

            // Fechas de creación y actualización automáticas (created_at, updated_at)
            $table->timestamps();
        });

        // --- Tabla pivote: cartas que el usuario OFRECE en el tradeo ---
        // Relación muchos a muchos entre tradeos y cartas
        // Un tradeo puede ofrecer varias cartas, y una carta puede estar en varios tradeos
        Schema::create('tradeo_cartas_ofrece', function (Blueprint $table) {
            $table->id();

            // Clave foránea al tradeo — si se borra el tradeo, se borran estas filas
            $table->foreignId('tradeo_id')->constrained('tradeos')->onDelete('cascade');

            // Clave foránea a la carta que se ofrece
            $table->foreignId('carta_id')->constrained('cartas')->onDelete('cascade');
        });

        // --- Tabla pivote: cartas que el usuario BUSCA en el tradeo ---
        // Misma estructura que la anterior pero para las cartas deseadas
        Schema::create('tradeo_cartas_busca', function (Blueprint $table) {
            $table->id();

            // Clave foránea al tradeo
            $table->foreignId('tradeo_id')->constrained('tradeos')->onDelete('cascade');

            // Clave foránea a la carta que se busca
            $table->foreignId('carta_id')->constrained('cartas')->onDelete('cascade');
        });
    }

    // Si se revierte la migración, se eliminan las tablas en orden inverso
    // para respetar las dependencias de claves foráneas
    public function down(): void
    {
        Schema::dropIfExists('tradeo_cartas_busca');  // Primero las tablas dependientes
        Schema::dropIfExists('tradeo_cartas_ofrece'); // Luego la otra tabla dependiente
        Schema::dropIfExists('tradeos');              // Por último la tabla principal
    }
};