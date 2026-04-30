<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla principal de tradeos
        Schema::create('tradeos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('descripcion')->nullable();
            $table->string('estado')->default('activo');
            $table->timestamps();
        });

        // Cartas que el usuario ofrece
        Schema::create('tradeo_cartas_ofrece', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tradeo_id')->constrained('tradeos')->onDelete('cascade');
            $table->foreignId('carta_id')->constrained('cartas')->onDelete('cascade');
        });

        // Cartas que el usuario busca
        Schema::create('tradeo_cartas_busca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tradeo_id')->constrained('tradeos')->onDelete('cascade');
            $table->foreignId('carta_id')->constrained('cartas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tradeo_cartas_busca');
        Schema::dropIfExists('tradeo_cartas_ofrece');
        Schema::dropIfExists('tradeos');
    }
};