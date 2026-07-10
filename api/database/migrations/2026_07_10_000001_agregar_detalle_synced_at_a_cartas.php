<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Marca de hidratación del detalle en la tabla cartas ---
        // Las cartas cacheadas desde el resumen de un set (cache-aside de
        // GET /api/sets/{id}/cartas) solo traen nombre, número e imagen.
        // El resto (rareza, tipo, precio, descripción...) se completa desde
        // TCGdex la primera vez que alguien abre el detalle de la carta, y
        // esta marca registra cuándo ocurrió (null = aún sin hidratar).
        //
        // Las cartas ya seedeadas quedan a null a propósito: en su primera
        // visita se re-hidratan una vez y de paso refrescan su precio.
        Schema::table('cartas', function (Blueprint $table) {
            $table->timestamp('detalle_synced_at')->nullable();
        });
    }

    // Si se revierte la migración, se elimina la columna nueva
    public function down(): void
    {
        Schema::table('cartas', function (Blueprint $table) {
            $table->dropColumn('detalle_synced_at');
        });
    }
};
