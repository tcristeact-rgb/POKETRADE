<?php

namespace App\Console\Commands;

use App\Models\Carta;
use App\Services\TcgdexService;
use App\Support\CatalogoTcg;
use Illuminate\Console\Command;

class SincronizarCartasTcgdex extends Command
{
    // Nombre del comando para ejecutarlo desde la terminal
    // Uso: php artisan cartas:sincronizar-tcgdex [--solo-precios]
    protected $signature = 'cartas:sincronizar-tcgdex
                            {--solo-precios : Actualiza únicamente el precio de Cardmarket}';

    // Descripción que aparece al hacer php artisan list
    protected $description = 'Sincroniza el catálogo de cartas con TCGdex (precios, imágenes y datos). Nota: el caché de la API dura 24 h.';

    public function handle(TcgdexService $tcgdex)
    {
        // Solo sincronizamos cartas que vienen de TCGdex; las filas
        // creadas a mano por un admin (sin tcgdex_id) no se tocan
        $cartas = Carta::whereNotNull('tcgdex_id')->get();

        if ($cartas->isEmpty()) {
            $this->warn('No hay cartas TCGdex en la BD. Ejecuta antes el seeder: php artisan db:seed --class=CartasSeeder');
            return self::FAILURE;
        }

        $actualizadas = 0;

        foreach ($cartas as $carta) {
            $datos = $tcgdex->obtenerCarta($carta->tcgdex_id);

            if (!$datos) {
                $this->error("Error en: {$carta->nombre} ({$carta->tcgdex_id})");
                continue;
            }

            $precio = $datos['pricing']['cardmarket']['avg']
                ?? $datos['pricing']['cardmarket']['trend']
                ?? null;

            if ($this->option('solo-precios')) {
                $carta->update(['precio_cardmarket' => $precio]);
            } else {
                // TCGdex devuelve el tipo y la rareza como texto ya traducido
                // ("Fire"/"Fuego"): se normalizan a la clave canónica, que es
                // lo único que guarda la BD
                $carta->update([
                    'nombre'            => $datos['name'],
                    'tipo_key'          => CatalogoTcg::claveTipo($datos['types'][0] ?? null),
                    'rareza_key'        => CatalogoTcg::claveRareza($datos['rarity'] ?? null),
                    'numero'            => $datos['localId'] ?? $carta->numero,
                    'imagen_url'        => $datos['image'] ?? $carta->imagen_url,
                    'descripcion'       => $datos['description'] ?? $carta->descripcion,
                    'ilustrador'        => $datos['illustrator'] ?? $carta->ilustrador,
                    'hp'                => $datos['hp'] ?? null,
                    'precio_cardmarket' => $precio,
                ]);
            }

            $actualizadas++;

            // Pausa breve entre peticiones: uso considerado de la API
            usleep(100_000);
        }

        // Mensaje final cuando el proceso ha terminado
        $this->info("Sincronizadas {$actualizadas} de {$cartas->count()} cartas.");

        return self::SUCCESS;
    }
}
