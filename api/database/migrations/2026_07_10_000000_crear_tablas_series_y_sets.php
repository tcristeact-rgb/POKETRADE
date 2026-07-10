<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Tabla series ---
        // Índice de series del TCG (Base, XY, Espada y Escudo...), sembrado
        // desde TCGdex por el comando tcgdex:sync-sets. Es un índice ligero
        // (~25 filas): aquí NO se descargan cartas por adelantado.
        Schema::create('series', function (Blueprint $table) {
            $table->id();

            // ID de la serie en TCGdex (ej: "sv", "swsh") — identidad
            // natural y clave de deduplicación del comando de sync
            $table->string('tcgdex_id')->unique();

            // Nombre de la serie (ej: "Escarlata y Púrpura")
            $table->string('nombre');

            // Logo de la serie — asset de TCGdex SIN extensión (como las
            // imágenes de carta); puede faltar en la API
            $table->text('logo_url')->nullable();

            $table->timestamps();
        });

        // --- Tabla sets ---
        // Índice de sets de expansión (~170 filas), también ligero: las
        // cartas de cada set se cachean bajo demanda en la tabla cartas
        // la primera vez que alguien abre ese set (cache-aside), y
        // synced_at marca cuándo se cachearon.
        Schema::create('sets', function (Blueprint $table) {
            $table->id();

            // ID del set en TCGdex (ej: "sv03.5") — coincide con el campo
            // set_id que ya existe en la tabla cartas
            $table->string('tcgdex_id')->unique();

            // Serie a la que pertenece el set; si se borra la serie caen
            // sus sets (el índice se regenera entero con el comando)
            $table->foreignId('serie_id')->constrained('series')->cascadeOnDelete();

            // Nombre del set (ej: "151", "Cénit Supremo")
            $table->string('nombre');

            // Logo y símbolo del set — assets de TCGdex sin extensión.
            // Muchos sets antiguos no tienen logo pero sí símbolo, por eso
            // guardamos ambos: el frontend usa el símbolo como respaldo
            $table->text('logo_url')->nullable();
            $table->text('simbolo_url')->nullable();

            // Nº total de cartas del set (incluye las secretas): es lo que
            // el usuario verá en el grid al abrir el set
            $table->unsignedSmallInteger('numero_cartas')->default(0);

            // Fecha de lanzamiento — indexada porque las vistas ordenan
            // por "más recientes primero"; puede faltar en la API
            $table->date('fecha_lanzamiento')->nullable()->index();

            // Marca de frescura del cache-aside: null = las cartas del set
            // aún no se han descargado a la tabla cartas
            $table->timestamp('synced_at')->nullable();

            $table->timestamps();
        });
    }

    // Si se revierte la migración, se eliminan ambas tablas
    // (sets primero por la clave foránea hacia series)
    public function down(): void
    {
        Schema::dropIfExists('sets');
        Schema::dropIfExists('series');
    }
};
