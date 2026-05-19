<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Ejecuta todos los seeders en el orden correcto
        // El orden importa por las dependencias entre tablas:
        // 1. Primero las cartas (no dependen de nada)
        // 2. Luego los usuarios (no dependen de nada)
        // 3. Luego el inventario (depende de usuarios y cartas)
        // 4. Por último los tradeos (depende de usuarios y cartas)
        $this->call([
            CartasSeeder::class,
            UsuariosSeeder::class,
            InventarioSeeder::class,
            TradeosSeeder::class,
        ]);
    }
}