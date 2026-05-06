<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Tabla users ---
        // Almacena todos los usuarios registrados en PokeTrade
        // Es la tabla central del sistema, referenciada por inventario y tradeos
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // ID autoincremental, clave primaria

            // Nombre del usuario (ej: "Daniel") — obligatorio
            $table->string('nombre');

            // Apellido del usuario (ej: "Leal") — obligatorio
            $table->string('apellido');

            // Email único, usado para login — obligatorio
            // unique() garantiza que no haya dos usuarios con el mismo email
            $table->string('email')->unique();

            // Contraseña encriptada con bcrypt — obligatorio
            // Nunca se almacena en texto plano
            $table->string('password');

            // Rol del usuario: 'cliente' o 'admin'
            // Por defecto todos los usuarios nuevos son 'cliente'
            // Los admin pueden crear/editar/borrar cartas y gestionar usuarios
            $table->string('rol')->default('cliente');

            // Fecha de nacimiento — opcional
            $table->date('fecha_nacimiento')->nullable();

            // Nacionalidad del usuario — opcional, máximo 100 caracteres
            $table->string('nacionalidad', 100)->nullable();

            // URL de la foto de perfil del usuario — opcional
            // Se usa text en lugar de string para URLs largas
            $table->text('avatar_url')->nullable();

            // Token para la función "recuérdame" del login — generado por Laravel
            $table->rememberToken();

            // Fechas de creación y actualización automáticas (created_at, updated_at)
            $table->timestamps();
        });
    }

    // Si se revierte la migración, se elimina la tabla completa
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};