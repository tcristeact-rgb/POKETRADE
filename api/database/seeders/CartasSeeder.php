<?php

namespace Database\Seeders;

use App\Models\Carta;
use App\Services\TcgdexService;
use Illuminate\Database\Seeder;

class CartasSeeder extends Seeder
{
    // Sets curados que forman el catálogo inicial (~437 cartas TCG reales):
    //   sv03.5   → "151" (Escarlata y Púrpura: los 151 Pokémon originales)
    //   swsh12.5 → "Cénit Supremo" (cierre de la era Espada y Escudo)
    // Para ampliar el catálogo basta con añadir IDs de set de TCGdex aquí
    // y volver a ejecutar el seeder (es idempotente: actualiza por tcgdex_id).
    private const SETS = ['sv03.5', 'swsh12.5'];

    // Este seeder NO crea datos de prueba, siembra el catálogo real.
    // En producción se ejecuta directamente (salta el guard de entorno
    // de DatabaseSeeder): php artisan db:seed --class=CartasSeeder --force
    public function run(): void
    {
        $tcgdex = app(TcgdexService::class);

        foreach (self::SETS as $setId) {
            $set = $tcgdex->obtenerSet($setId);

            if (!$set || empty($set['cards'])) {
                $this->command->error("No se pudo obtener el set {$setId} de TCGdex. ¿Hay conexión?");
                continue;
            }

            $total = count($set['cards']);
            $this->command->info("Sembrando set \"{$set['name']}\" ({$total} cartas)...");

            foreach ($set['cards'] as $i => $resumen) {
                // El resumen del set solo trae id/localId/name/image; el
                // detalle añade rareza, tipos, ilustrador, hp y precio
                $carta = $tcgdex->obtenerCarta($resumen['id']);

                if (!$carta) {
                    $this->command->warn("  Sin datos, omitida: {$resumen['id']}");
                    continue;
                }

                // updateOrCreate por tcgdex_id → re-ejecutable sin duplicar
                Carta::updateOrCreate(
                    ['tcgdex_id' => $carta['id']],
                    [
                        'nombre'            => $carta['name'],
                        'tipo'              => $carta['types'][0] ?? null,
                        'rareza'            => $carta['rarity'] ?? null,
                        'set_expansion'     => $carta['set']['name'] ?? $set['name'],
                        'set_id'            => $carta['set']['id'] ?? $setId,
                        'numero'            => $carta['localId'] ?? null,
                        'imagen_url'        => $carta['image'] ?? null,
                        'descripcion'       => $carta['description'] ?? null,
                        'ilustrador'        => $carta['illustrator'] ?? null,
                        'hp'                => $carta['hp'] ?? null,
                        'precio_cardmarket' => $carta['pricing']['cardmarket']['avg']
                                                ?? $carta['pricing']['cardmarket']['trend']
                                                ?? null,
                    ]
                );

                // Progreso cada 50 cartas para saber que sigue vivo
                if (($i + 1) % 50 === 0) {
                    $this->command->info('  ' . ($i + 1) . " / {$total}");
                }

                // Pausa breve entre peticiones: uso considerado de la API
                // (en re-ejecuciones el caché evita las peticiones HTTP)
                usleep(100_000);
            }
        }

        $sembradas = Carta::whereNotNull('tcgdex_id')->count();
        $this->command->info("Catálogo TCGdex completo: {$sembradas} cartas en la BD.");
    }
}
