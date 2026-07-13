<?php

namespace App\Console\Commands;

use App\Models\Carta;
use App\Services\TcgdexService;
use App\Support\CatalogoTcg;
use App\Support\Idiomas;
use Illuminate\Console\Command;

class SincronizarCartasTcgdex extends Command
{
    // Nombre del comando para ejecutarlo desde la terminal
    // Uso: php artisan cartas:sincronizar-tcgdex [--solo-precios] [--idioma=en]
    protected $signature = 'cartas:sincronizar-tcgdex
                            {--solo-precios : Actualiza únicamente el precio de Cardmarket}
                            {--idioma= : Catálogo del que traer los textos (por defecto, el de la app)}';

    // Descripción que aparece al hacer php artisan list
    protected $description = 'Sincroniza el catálogo de cartas con TCGdex (precios, imágenes y datos). Nota: el caché de la API dura 24 h.';

    public function handle(TcgdexService $tcgdex)
    {
        $idioma = $this->option('idioma') ?: config('app.locale');

        if (!Idiomas::soportado($idioma)) {
            $this->error("Idioma no soportado: \"{$idioma}\". Los que hay: " . implode(', ', Idiomas::SOPORTADOS) . '.');
            return self::FAILURE;
        }

        // Solo sincronizamos cartas que vienen de TCGdex; las filas
        // creadas a mano por un admin (sin tcgdex_id) no se tocan
        $cartas = Carta::whereNotNull('tcgdex_id')->get();

        if ($cartas->isEmpty()) {
            $this->warn('No hay cartas TCGdex en la BD. Ejecuta antes el seeder: php artisan db:seed --class=CartasSeeder');
            return self::FAILURE;
        }

        $actualizadas = 0;
        $ausentes     = 0;

        foreach ($cartas as $carta) {
            $datos = $tcgdex->obtenerCarta($carta->tcgdex_id, $idioma);

            // null = TCGdex no contestó. Es un fallo y se avisa.
            if ($datos === null) {
                $this->error("Error en: {$carta->nombre} ({$carta->tcgdex_id})");
                continue;
            }

            // [] = ese catálogo no tiene la carta, y no la va a tener: de los
            // sets clásicos no existe versión española. No es un fallo — se
            // anota el intento para no volver a preguntar y se sigue.
            if ($datos === []) {
                $carta->update([
                    'idiomas_detallados' => array_values(array_unique([
                        ...($carta->idiomas_detallados ?? []),
                        $idioma,
                    ])),
                ]);

                $ausentes++;
                continue;
            }

            $precio = $datos['pricing']['cardmarket']['avg']
                ?? $datos['pricing']['cardmarket']['trend']
                ?? null;

            if ($this->option('solo-precios')) {
                $carta->update(['precio_cardmarket' => $precio]);
            } else {
                // Los textos van a las columnas de ESTE catálogo; el tipo y la
                // rareza llegan traducidos ("Fire"/"Fuego") y se normalizan a
                // la clave canónica, que es lo único que guarda la BD.
                $carta->update([
                    "nombre_{$idioma}"      => $datos['name'],
                    "descripcion_{$idioma}" => $datos['description'] ?? $carta->{"descripcion_{$idioma}"},
                    "imagen_{$idioma}"      => $datos['image'] ?? $carta->{"imagen_{$idioma}"},
                    'idiomas_detallados'    => array_values(array_unique([
                        ...($carta->idiomas_detallados ?? []),
                        $idioma,
                    ])),
                    'tipo_key'              => CatalogoTcg::claveTipo($datos['types'][0] ?? null),
                    'rareza_key'            => CatalogoTcg::claveRareza($datos['rarity'] ?? null),
                    'numero'                => $datos['localId'] ?? $carta->numero,
                    'ilustrador'            => $datos['illustrator'] ?? $carta->ilustrador,
                    'hp'                    => $datos['hp'] ?? null,
                    'precio_cardmarket'     => $precio,
                    'detalle_synced_at'     => now(),
                ]);
            }

            $actualizadas++;

            // Pausa breve entre peticiones: uso considerado de la API
            usleep(100_000);
        }

        // Mensaje final cuando el proceso ha terminado
        $this->info("Sincronizadas {$actualizadas} de {$cartas->count()} cartas desde el catálogo \"{$idioma}\".");

        if ($ausentes > 0) {
            $this->line("{$ausentes} no existen en ese catálogo (sets clásicos sin traducir): se sirven en el idioma que tengan.");
        }

        return self::SUCCESS;
    }
}
