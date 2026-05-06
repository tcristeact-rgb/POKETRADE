<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('carta_id')->constrained('cartas')->onDelete('cascade');
            $table->unsignedSmallInteger('cantidad')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'carta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario');
    }
};