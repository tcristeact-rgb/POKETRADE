<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Campos TCGdex en la tabla cartas ---
        // La fuente de datos del catálogo pasa de PokeAPI a TCGdex
        // (https://api.tcgdex.net/v2), que sirve cartas TCG reales con
        // sus ilustraciones oficiales. Migración aditiva: todos los
        // campos nuevos son nullable para no romper filas existentes
        // (compatible con PostgreSQL en Supabase).
        Schema::table('cartas', function (Blueprint $table) {
            // ID de la carta en TCGdex (ej: "sv03.5-006") — identidad
            // natural de la carta y clave de deduplicación del seeder
            $table->string('tcgdex_id')->nullable()->unique();

            // ID del set en TCGdex (ej: "sv03.5") — indexado para poder
            // filtrar el catálogo por set
            $table->string('set_id')->nullable()->index();

            // Artista que ilustró la carta (puede faltar en la API)
            $table->string('ilustrador')->nullable();

            // Puntos de salud — solo cartas de Pokémon
            // (null en cartas de Entrenador y de Energía)
            $table->unsignedSmallInteger('hp')->nullable();

            // Precio medio de Cardmarket en EUR (pricing.cardmarket.avg)
            // Puede ser null: no todas las cartas tienen precio publicado
            $table->decimal('precio_cardmarket', 8, 2)->nullable();
        });
    }

    // Si se revierte la migración, se eliminan índices y columnas nuevas
    public function down(): void
    {
        Schema::table('cartas', function (Blueprint $table) {
            $table->dropUnique(['tcgdex_id']);
            $table->dropIndex(['set_id']);
            $table->dropColumn(['tcgdex_id', 'set_id', 'ilustrador', 'hp', 'precio_cardmarket']);
        });
    }
};
