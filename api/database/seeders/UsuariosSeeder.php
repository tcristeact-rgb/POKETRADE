<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UsuariosSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::create([
            'nombre'       => 'Admin',
            'apellido'     => 'PokeTrade',
            'email'        => 'admin@poketrade.es',
            'password'     => Hash::make('admin123'),
            'rol'          => 'admin',
            'nacionalidad' => 'Española',
        ]);

        // Usuarios de prueba
        User::create([
            'nombre'       => 'Teo',
            'apellido'     => 'Cristea',
            'email'        => 'teo@poketrade.es',
            'password'     => Hash::make('123456'),
            'rol'          => 'cliente',
            'nacionalidad' => 'Rumana',
        ]);

        User::create([
            'nombre'       => 'María',
            'apellido'     => 'García',
            'email'        => 'maria@poketrade.es',
            'password'     => Hash::make('123456'),
            'rol'          => 'cliente',
            'nacionalidad' => 'Española',
        ]);
    }
}