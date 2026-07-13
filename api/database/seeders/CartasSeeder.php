<?php

namespace Database\Seeders;

use App\Models\Carta;
use App\Services\TcgdexService;
use App\Support\CatalogoTcg;
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
    //
    // Siembra UN idioma, el de la app. Los otros no se pre-cargan: se rellenan
    // solos la primera vez que alguien abre el set en ese idioma (cache-aside
    // por idioma, ver SetController). Sembrar 437 cartas en un idioma que
    // quizá nadie visite serían 437 peticiones tiradas.
    public function run(): void
    {
        $tcgdex = app(TcgdexService::class);
        $idioma = config('app.locale');

        foreach (self::SETS as $setId) {
            $set = $tcgdex->obtenerSet($setId, $idioma);

            if (empty($set['cards'])) {
                $this->command->error("No se pudo obtener el set {$setId} de TCGdex. ¿Hay conexión?");
                continue;
            }

            $total = count($set['cards']);
            $this->command->info("Sembrando set \"{$set['name']}\" ({$total} cartas) en \"{$idioma}\"...");

            foreach ($set['cards'] as $i => $resumen) {
                // El resumen del set solo trae id/localId/name/image; el
                // detalle añade rareza, tipos, ilustrador, hp y precio
                $carta = $tcgdex->obtenerCarta($resumen['id'], $idioma);

                if (empty($carta)) {
                    $this->command->warn("  Sin datos, omitida: {$resumen['id']}");
                    continue;
                }

                // updateOrCreate por tcgdex_id → re-ejecutable sin duplicar
                Carta::updateOrCreate(
                    ['tcgdex_id' => $carta['id']],
                    [
                        "nombre_{$idioma}"      => $carta['name'],
                        "descripcion_{$idioma}" => $carta['description'] ?? null,
                        "imagen_{$idioma}"      => $carta['image'] ?? null,
                        'idiomas_detallados'    => [$idioma],
                        'tipo_key'              => CatalogoTcg::claveTipo($carta['types'][0] ?? null),
                        'rareza_key'            => CatalogoTcg::claveRareza($carta['rarity'] ?? null),
                        'set_id'                => $carta['set']['id'] ?? $setId,
                        'numero'                => $carta['localId'] ?? null,
                        'ilustrador'            => $carta['illustrator'] ?? null,
                        'hp'                    => $carta['hp'] ?? null,
                        'precio_cardmarket'     => $carta['pricing']['cardmarket']['avg']
                                                    ?? $carta['pricing']['cardmarket']['trend']
                                                    ?? null,
                        'detalle_synced_at'     => now(),
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
