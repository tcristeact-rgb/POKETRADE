<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash; // Para encriptar contraseñas
use App\Models\User;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        // --- Usuario administrador ---
        // Tiene rol 'admin' para poder acceder a las rutas protegidas por EsAdmin
        User::create([
            'nombre'       => 'Admin',
            'apellido'     => 'PokeTrade',
            'email'        => 'admin@poketrade.es',
            'password'     => Hash::make('admin123'), // Contraseña encriptada con bcrypt
            'rol'          => 'admin',
            'nacionalidad' => 'Española',
        ]);

        // --- Usuario de prueba: Teo ---
        // Rol 'cliente', puede crear tradeos y gestionar su inventario
        User::create([
            'nombre'       => 'Teo',
            'apellido'     => 'Cristea',
            'email'        => 'teo@poketrade.es',
            'password'     => Hash::make('123456'), // Contraseña encriptada
            'rol'          => 'cliente',
            'nacionalidad' => 'Rumana',
        ]);

        // --- Usuario de prueba: María ---
        // Rol 'cliente', puede crear tradeos y gestionar su inventario
        User::create([
            'nombre'       => 'María',
            'apellido'     => 'García',
            'email'        => 'maria@poketrade.es',
            'password'     => Hash::make('123456'), // Contraseña encriptada
            'rol'          => 'cliente',
            'nacionalidad' => 'Española',
        ]);
    }
}