<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

// --- Todo lo que hay que hacer al arrancar el contenedor, en UN SOLO proceso ---
//
// Cada `php artisan` es un arranque completo de Laravel: leer la config, resolver
// los service providers, registrar las rutas. Medido en una CPU normal son ~450 ms
// por invocación, y Render (plan free) da 0,1 CPU — o sea, unos 5 s cada una.
//
// El CMD del Dockerfile encadenaba CUATRO (config:cache, route:cache, event:cache,
// migrate), y el puerto no se abre hasta que terminan todas: ~17 s de arranque en
// frío antes de escuchar a nadie, en un servicio que ya tarda lo suyo en levantarse.
//
// De esos cuatro, tres no dependen del entorno y se han ido al BUILD de la imagen
// (ver Dockerfile). Los dos que quedan sí lo necesitan —config:cache congela las
// variables de entorno, y en Render solo existen en tiempo de ejecución— así que se
// hacen aquí, compartiendo un único arranque en vez de pagar uno cada uno.
class Arrancar extends Command
{
    protected $signature = 'app:arrancar';

    protected $description = 'Prepara la app para servir: cachea la configuración y aplica las migraciones pendientes. Un solo arranque de Laravel para las dos cosas.';

    public function handle(): int
    {
        // La caché de configuración. No puede generarse en el build porque congela
        // los valores de las variables de entorno, y ahí todavía no existen: se
        // guardarían las credenciales de Supabase a null y la app no arrancaría.
        if ($this->call('config:cache') !== self::SUCCESS) {
            $this->error('No se pudo cachear la configuración.');
            return self::FAILURE;
        }

        // Migraciones. Es idempotente: si ya están aplicadas no hace nada, así que
        // es seguro ejecutarlo en cada despertar. --force porque el entorno no es
        // interactivo y Laravel, si no, pediría confirmación.
        //
        // Va DESPUÉS de config:cache a propósito: así se conecta a la base de datos
        // con la configuración que de verdad va a usar el servidor, y si esas
        // credenciales estuvieran mal, el contenedor falla aquí — ruidosamente — en
        // vez de arrancar y devolver errores 500 a los visitantes.
        if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
            $this->error('Las migraciones fallaron.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
