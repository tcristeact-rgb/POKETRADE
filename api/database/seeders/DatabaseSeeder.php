<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Guard de entorno: estos seeders crean usuarios de prueba con
        // credenciales fijas (hardcodeadas). NUNCA deben ejecutarse en
        // producción. Si no estamos en entorno local, avisamos y salimos
        // sin insertar nada: la base de datos de producción arranca vacía
        // y se llena solo con usuarios reales registrados vía la API.
        if (! app()->environment('local')) {
            $this->command->warn('Seeders omitidos: solo se ejecutan en entorno local.');
            return;
        }

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