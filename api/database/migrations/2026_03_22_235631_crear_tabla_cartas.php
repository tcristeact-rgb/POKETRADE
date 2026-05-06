<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Tabla cartas ---
        // Almacena todas las cartas Pokémon disponibles en la plataforma
        // Los datos vienen del seeder o de la PokeAPI
        Schema::create('cartas', function (Blueprint $table) {
            $table->id(); // ID autoincremental, clave primaria

            // Nombre de la carta (ej: "Charizard") — obligatorio
            $table->string('nombre');

            // Tipo del Pokémon (ej: "Fuego", "Agua", "Psíquico") — opcional
            $table->string('tipo')->nullable();

            // Rareza de la carta (ej: "Común", "Rara Holo", "Ultra Rara") — opcional
            $table->string('rareza')->nullable();

            // Set o expansión a la que pertenece (ej: "Base Set", "Fossil") — opcional
            $table->string('set_expansion')->nullable();

            // Número de la carta dentro del set (ej: "006", "025") — opcional
            $table->string('numero')->nullable();

            // URL de la imagen de la carta, obtenida desde la PokeAPI — opcional
            // Se usa text en lugar de string para URLs largas
            $table->text('imagen_url')->nullable();

            // Descripción de la carta — opcional
            $table->text('descripcion')->nullable();

            // Fechas de creación y actualización automáticas (created_at, updated_at)
            $table->timestamps();
        });
    }

    // Si se revierte la migración, se elimina la tabla completa
    public function down(): void
    {
        Schema::dropIfExists('cartas');
    }
};