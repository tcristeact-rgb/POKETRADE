<?php

namespace App\Console\Commands;

use App\Models\Serie;
use App\Models\Set;
use App\Services\TcgdexService;
use Illuminate\Console\Command;

class SincronizarSetsTcgdex extends Command
{
    // Nombre del comando para ejecutarlo desde la terminal
    // Uso: php artisan tcgdex:sync-sets
    protected $signature = 'tcgdex:sync-sets';

    // Descripción que aparece al hacer php artisan list
    protected $description = 'Sincroniza el índice de series y sets de expansión desde TCGdex (idempotente). Las cartas NO se descargan aquí: se cachean bajo demanda al abrir cada set.';

    public function handle(TcgdexService $tcgdex)
    {
        // Dos pasadas: español primero (nombres localizados y refresco de
        // lo existente) e inglés después, que SOLO añade las series y sets
        // que el catálogo "es" de TCGdex omite por no haberse traducido
        // nunca (Gym, e-Card, Wizards Promos, Base 4 y 5...)
        if (!$this->sincronizarIdioma($tcgdex, 'es')) {
            $this->error('No se pudo obtener la lista de series de TCGdex. ¿Hay conexión?');
            return self::FAILURE;
        }

        if (!$this->sincronizarIdioma($tcgdex, 'en')) {
            $this->warn('El catálogo inglés no respondió: índice sincronizado solo con el catálogo español.');
        }

        $this->info('Índice sincronizado: ' . Serie::count() . ' series y ' . Set::count() . ' sets en la BD.');

        return self::SUCCESS;
    }

    // Sincroniza el índice de un idioma. En "es" refresca todo; en los
    // demás solo crea lo que falte, sin pisar los nombres en español.
    // Devuelve false si la lista de series no se pudo obtener.
    private function sincronizarIdioma(TcgdexService $tcgdex, string $idioma): bool
    {
        $series = $tcgdex->listarSeries($idioma);

        if (!$series) {
            return false;
        }

        $soloFaltantes = $idioma !== 'es';
        $excluidas     = config('tcgdex.series_excluidas', []);
        $this->info('Sincronizando ' . count($series) . " series del catálogo \"{$idioma}\"...");

        foreach ($series as $resumen) {
            // Series excluidas por config (Pocket, McDonald's...): ni se
            // importan ni se actualizan
            if (in_array($resumen['id'], $excluidas, true)) {
                $this->line("Serie excluida por config, omitida: {$resumen['id']}");
                continue;
            }

            // El detalle de la serie añade el logo y la lista de sets
            $detalle = $tcgdex->obtenerSerie($resumen['id'], $idioma);

            if (!$detalle) {
                $this->warn("Sin datos, omitida la serie: {$resumen['id']}");
                continue;
            }

            // Cascada de logo de serie (1º y 2º eslabón): el catálogo
            // español no trae logo para la mayoría de series antiguas
            // aunque el inglés sí; el 3º eslabón (logo del set más
            // reciente) se aplica tras sincronizar sus sets
            $logo = $detalle['logo'] ?? null;
            if (!$logo && $idioma !== 'en') {
                $logo = $tcgdex->obtenerSerie($detalle['id'], 'en')['logo'] ?? null;
            }

            $datosSerie = [
                'nombre'   => $detalle['name'],
                'logo_url' => $logo,
            ];

            // updateOrCreate por tcgdex_id → re-ejecutable sin duplicar;
            // firstOrCreate en la pasada inglesa → no pisa el español
            $serie = $soloFaltantes
                ? Serie::firstOrCreate(['tcgdex_id' => $detalle['id']], $datosSerie)
                : Serie::updateOrCreate(['tcgdex_id' => $detalle['id']], $datosSerie);

            $sets   = $detalle['sets'] ?? [];
            $nuevos = 0;

            if (!$soloFaltantes) {
                $this->info("Serie \"{$serie->nombre}\" (" . count($sets) . ' sets)...');
            }

            foreach ($sets as $resumenSet) {
                // En la pasada de complemento, los sets ya sincronizados
                // se saltan antes de pedir su detalle: cero peticiones
                if ($soloFaltantes && Set::where('tcgdex_id', $resumenSet['id'])->exists()) {
                    continue;
                }

                // El resumen del set no trae la fecha de lanzamiento; solo
                // el detalle. La petición extra queda en el caché de 24 h
                // del servicio, que además adelanta trabajo al cache-aside
                // de cartas (GET /api/sets/{id}/cartas usa esta respuesta)
                $detalleSet = $tcgdex->obtenerSet($resumenSet['id']);

                // Nota: synced_at NO se toca aquí — esa marca pertenece al
                // cacheo de cartas y debe sobrevivir a re-sincronizaciones
                Set::updateOrCreate(
                    ['tcgdex_id' => $resumenSet['id']],
                    [
                        'serie_id'          => $serie->id,
                        'nombre'            => $detalleSet['name'] ?? $resumenSet['name'],
                        'logo_url'          => $detalleSet['logo'] ?? $resumenSet['logo'] ?? null,
                        'simbolo_url'       => $detalleSet['symbol'] ?? $resumenSet['symbol'] ?? null,
                        'numero_cartas'     => $detalleSet['cardCount']['total'] ?? $resumenSet['cardCount']['total'] ?? 0,
                        'fecha_lanzamiento' => $detalleSet['releaseDate'] ?? null,
                    ]
                );

                $nuevos++;

                // Pausa breve entre peticiones: uso considerado de la API
                // (en re-ejecuciones el caché evita las peticiones HTTP)
                usleep(100_000);
            }

            if ($soloFaltantes && $nuevos > 0) {
                $this->info("Serie \"{$serie->nombre}\": +{$nuevos} sets del catálogo \"{$idioma}\"");
            }

            // Cascada de logo de serie (3º eslabón): si sigue sin logo
            // en ambos idiomas, hereda el del set más reciente que
            // tenga; si ninguno tiene, queda null → placeholder
            if (!$serie->logo_url) {
                $setConLogo = $serie->sets()
                    ->whereNotNull('logo_url')
                    ->orderByRaw('fecha_lanzamiento IS NULL')
                    ->orderByDesc('fecha_lanzamiento')
                    ->first();

                if ($setConLogo) {
                    $serie->update(['logo_url' => $setConLogo->logo_url]);
                }
            }
        }

        return true;
    }
}
