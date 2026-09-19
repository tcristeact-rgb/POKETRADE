<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Índices en las columnas de clave foránea que no lo tenían.
//
// foreignId()->constrained() crea la constraint, pero ni Postgres ni SQLite
// crean un índice sobre la columna que referencia (MySQL sí, de ahí la confusión
// habitual). Sin él, cada cascade delete del padre y cada whereIn sobre estas
// columnas es un seq scan de la tabla hija: al borrar una carta, Postgres recorre
// entero inventario y los dos pivotes de tradeos POR CADA fila borrada; al cargar
// un tradeo con sus cartas (with('cartasOfrece', 'cartasBusca')), recorre los
// pivotes enteros.
//
// Quedan fuera a propósito:
//   - cartas.set_id: ya tiene índice (2026_07_04_100000).
//   - inventario.user_id: lo cubre el unique (user_id, carta_id) porque va primero.
//     carta_id sí lo necesita, porque no es la columna líder de ese unique.
//
// Sin CONCURRENTLY: las tablas son diminutas y la migración corre en app:arrancar
// con una sola instancia y sin tráfico; el SHARE lock dura segundos.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tradeos', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('tradeo_cartas_ofrece', function (Blueprint $table) {
            $table->index('tradeo_id');
            $table->index('carta_id');
        });

        Schema::table('tradeo_cartas_busca', function (Blueprint $table) {
            $table->index('tradeo_id');
            $table->index('carta_id');
        });

        Schema::table('inventario', function (Blueprint $table) {
            $table->index('carta_id');
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->index('serie_id');
        });
    }

    public function down(): void
    {
        Schema::table('tradeos', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('tradeo_cartas_ofrece', function (Blueprint $table) {
            $table->dropIndex(['tradeo_id']);
            $table->dropIndex(['carta_id']);
        });

        Schema::table('tradeo_cartas_busca', function (Blueprint $table) {
            $table->dropIndex(['tradeo_id']);
            $table->dropIndex(['carta_id']);
        });

        Schema::table('inventario', function (Blueprint $table) {
            $table->dropIndex(['carta_id']);
        });

        Schema::table('sets', function (Blueprint $table) {
            $table->dropIndex(['serie_id']);
        });
    }
};
