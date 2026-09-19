<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Guard de entorno: el seeder crea usuarios de prueba con credenciales
        // fijas (hardcodeadas). NUNCA debe ejecutarse en producción. Si no
        // estamos en entorno local, avisamos y salimos sin insertar nada.
        if (! app()->environment('local')) {
            $this->command->warn('Seeders omitidos: solo se ejecutan en entorno local.');
            return;
        }

        // Solo usuarios. El catálogo de cartas NO se siembra: se cachea bajo
        // demanda desde TCGdex la primera vez que alguien abre cada set
        // (cache-aside, ver SetController). Series y sets los deja navegables
        // `php artisan tcgdex:sync-sets`.
        $this->call([
            UsuariosSeeder::class,
        ]);
    }
}
