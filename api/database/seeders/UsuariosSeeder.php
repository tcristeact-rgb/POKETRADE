<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash; // Para encriptar contraseñas
use App\Models\User;

// Usuarios de prueba para desarrollar en local, con contraseñas fijas.
//
// SOLO en local: DatabaseSeeder no los llama fuera de ese entorno (comprueba
// app()->environment('local') y sale sin insertar nada). En producción no se
// siembra nada, y quien quiera probar la demo se registra.
class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        // Mismo guard que DatabaseSeeder, por si algún día se llama a este seeder
        // directamente (db:seed --class) o se reestructura aquel y pierde el suyo.
        if (! app()->environment('local')) {
            return;
        }

        // --- Usuario administrador ---
        // Tiene rol 'admin' para poder acceder a las rutas protegidas por EsAdmin
        User::create([
            'nombre'       => 'Admin',
            'apellido'     => 'PokeTrade',
            'email'        => 'admin@poketrade.es',
            'password'     => Hash::make('admin123'), // Contraseña encriptada con bcrypt
            'rol'          => 'admin',
            'nacionalidad' => 'Española',
            'email_verified_at' => now(), // demo local: sin verificar no se puede iniciar sesión
        ]);

        // --- Usuario de prueba: Teo ---
        // Rol 'cliente', puede crear tradeos y gestionar su inventario
        User::create([
            'nombre'       => 'Teo',
            'apellido'     => 'Cristea',
            'email'        => 'teo@poketrade.es',
            'password'     => Hash::make('12345678'), // Contraseña encriptada
            'rol'          => 'cliente',
            'nacionalidad' => 'Rumana',
            'email_verified_at' => now(), // demo local: sin verificar no se puede iniciar sesión
        ]);

        // --- Usuario de prueba: María ---
        // Rol 'cliente', puede crear tradeos y gestionar su inventario
        User::create([
            'nombre'       => 'María',
            'apellido'     => 'García',
            'email'        => 'maria@poketrade.es',
            'password'     => Hash::make('12345678'), // Contraseña encriptada
            'rol'          => 'cliente',
            'nacionalidad' => 'Española',
            'email_verified_at' => now(), // demo local: sin verificar no se puede iniciar sesión
        ]);
    }
}