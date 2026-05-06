<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Tabla inventario ---
        // Tabla pivote entre usuarios y cartas
        // Representa las cartas que cada usuario tiene en su colección personal
        Schema::create('inventario', function (Blueprint $table) {
            $table->id(); // ID autoincremental, clave primaria

            // Clave foránea al usuario propietario de las cartas
            // onDelete('cascade') → si se borra el usuario, se borra su inventario
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Clave foránea a la carta del inventario
            // onDelete('cascade') → si se borra la carta, desaparece del inventario
            $table->foreignId('carta_id')->constrained('cartas')->onDelete('cascade');

            // Número de copias que tiene el usuario de esa carta
            // unsignedSmallInteger → número positivo pequeño (0-65535), suficiente para cantidad de cartas
            // Por defecto 1, ya que al añadir una carta se asume que tienes al menos una
            $table->unsignedSmallInteger('cantidad')->default(1);

            // Fechas de creación y actualización automáticas
            $table->timestamps();

            // Restricción única: un usuario no puede tener la misma carta duplicada
            // Si quiere más copias, se incrementa el campo cantidad
            $table->unique(['user_id', 'carta_id']);
        });
    }

    // Si se revierte la migración, se elimina la tabla completa
    public function down(): void
    {
        Schema::dropIfExists('inventario');
    }
};