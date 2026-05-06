<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Tabla personal_access_tokens ---
        // Generada automáticamente por Laravel Sanctum
        // Aunque en PokeTrade usamos JWT (tymon/jwt-auth) para la autenticación,
        // Sanctum viene incluido en Laravel y crea esta tabla por defecto
        // No la usamos directamente pero es necesaria para que Laravel no dé errores
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id(); // ID autoincremental

            // morphs() crea dos columnas: tokenable_type y tokenable_id
            // Permite asociar el token a cualquier modelo (User, Admin, etc.)
            $table->morphs('tokenable');

            // Nombre descriptivo del token (ej: "token de Daniel")
            $table->text('name');

            // El token en sí, hasheado — único para evitar duplicados
            $table->string('token', 64)->unique();

            // Permisos del token (ej: ["read", "write"]) — opcional
            $table->text('abilities')->nullable();

            // Fecha de último uso del token — opcional
            $table->timestamp('last_used_at')->nullable();

            // Fecha de expiración del token — opcional, indexada para búsquedas rápidas
            $table->timestamp('expires_at')->nullable()->index();

            // Fechas de creación y actualización automáticas
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Elimina la tabla si se revierte la migración
        Schema::dropIfExists('personal_access_tokens');
    }
};