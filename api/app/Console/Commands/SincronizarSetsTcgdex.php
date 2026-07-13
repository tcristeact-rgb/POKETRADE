<?php

namespace App\Console\Commands;

use App\Models\Serie;
use App\Models\Set;
use App\Services\TcgdexService;
use App\Support\Idiomas;
use Illuminate\Console\Command;

class SincronizarSetsTcgdex extends Command
{
    // Nombre del comando para ejecutarlo desde la terminal
    // Uso: php artisan tcgdex:sync-sets
    protected $signature = 'tcgdex:sync-sets';

    // Descripción que aparece al hacer php artisan list
    protected $description = 'Sincroniza el índice de series y sets de expansión desde TCGdex, en todos los idiomas (idempotente). Las cartas NO se descargan aquí: se cachean bajo demanda al abrir cada set.';

    public function handle(TcgdexService $tcgdex)
    {
        // Una pasada por catálogo. La primera monta el índice; las siguientes
        // rellenan el nombre en su idioma y añaden lo que ese catálogo tenga y
        // el anterior no: el español de TCGdex omite las series y los sets que
        // nunca se tradujeron (Gym, e-Card, Wizards Promos, Base 4 y 5...).
        //
        // Son 185 filas entre series y sets: recorrerlas en los dos idiomas es
        // barato, y es lo que hace que un inglés vea "Silver Tempest" donde un
        // español ve "Tempestad Plateada".
        foreach (Idiomas::SOPORTADOS as $posicion => $idioma) {
            $principal = $posicion === 0;

            if ($this->sincronizarIdioma($tcgdex, $idioma, $principal)) {
                continue;
            }

            if ($principal) {
                $this->error("No se pudo obtener la lista de series del catálogo \"{$idioma}\" de TCGdex. ¿Hay conexión?");
                return self::FAILURE;
            }

            $this->warn("El catálogo \"{$idioma}\" no respondió: sus nombres quedan sin actualizar.");
        }

        $this->info('Índice sincronizado: ' . Serie::count() . ' series y ' . Set::count() . ' sets en la BD.');

        return self::SUCCESS;
    }

    // Sincroniza el índice desde un catálogo. Cada pasada escribe SU columna de
    // nombre (nombre_es, nombre_en...); los campos neutros —logo, símbolo,
    // fecha, nº de cartas— los rellena la pasada principal, o la primera que
    // vea el set si es nuevo. Devuelve false si la lista de series no llegó.
    private function sincronizarIdioma(TcgdexService $tcgdex, string $idioma, bool $principal): bool
    {
        $series = $tcgdex->listarSeries($idioma);

        if (!$series) {
            return false;
        }

        $excluidas = config('tcgdex.series_excluidas', []);
        $this->info('Sincronizando ' . count($series) . " series del catálogo \"{$idioma}\"...");

        foreach ($series as $resumen) {
            // Series excluidas por config (Pocket, McDonald's...): ni se
            // importan ni se actualizan
            if (in_array($resumen['id'], $excluidas, true)) {
                $this->line("Serie excluida por config, omitida: {$resumen['id']}");
                continue;
            }

            // El detalle de la serie añade el logo y la lista de sets, con el
            // nombre de cada uno YA en este idioma: rellenar nombre_en no
            // cuesta ni una petición extra.
            $detalle = $tcgdex->obtenerSerie($resumen['id'], $idioma);

            if (empty($detalle)) {
                $this->warn("Sin datos, omitida la serie: {$resumen['id']}");
                continue;
            }

            $serie = Serie::firstOrNew(['tcgdex_id' => $detalle['id']]);
            $serie->{"nombre_{$idioma}"} = $detalle['name'];

            // Cascada de logo de serie (1º y 2º eslabón): el catálogo español no
            // trae logo para la mayoría de series antiguas aunque el inglés sí.
            // El 3º eslabón (heredar el del set más reciente) se aplica al final
            $logo = $detalle['logo']
                ?? ($idioma !== TcgdexService::COMPLETO
                    ? $tcgdex->obtenerSerie($detalle['id'], TcgdexService::COMPLETO)['logo'] ?? null
                    : null);

            if ($logo && ($principal || !$serie->logo_url)) {
                $serie->logo_url = $logo;
            }

            $serie->save();

            $sets   = $detalle['sets'] ?? [];
            $nuevos = 0;

            if ($principal) {
                $this->info("Serie \"{$serie->nombre}\" (" . count($sets) . ' sets)...');
            }

            foreach ($sets as $resumenSet) {
                $set = Set::firstOrNew(['tcgdex_id' => $resumenSet['id']]);

                $set->{"nombre_{$idioma}"} = $resumenSet['name'];
                $set->serie_id             = $serie->id;

                // El detalle del set (logo, símbolo, fecha, nº de cartas) es una
                // petición POR SET, y esos campos no dependen del idioma: solo
                // se pide si el set es nuevo o si es la pasada principal. Antes
                // la pasada de complemento se saltaba entero cualquier set que
                // ya existiera, y por eso el índice nunca tuvo nombres ingleses.
                if (!$set->exists || $principal) {
                    $detalleSet = $tcgdex->obtenerSet($resumenSet['id'], $idioma) ?: [];

                    $set->logo_url          = $detalleSet['logo'] ?? $resumenSet['logo'] ?? $set->logo_url;
                    $set->simbolo_url       = $detalleSet['symbol'] ?? $resumenSet['symbol'] ?? $set->simbolo_url;
                    $set->numero_cartas     = $detalleSet['cardCount']['total'] ?? $resumenSet['cardCount']['total'] ?? $set->numero_cartas ?? 0;
                    $set->fecha_lanzamiento = $detalleSet['releaseDate'] ?? $set->fecha_lanzamiento;

                    if (!$set->exists) {
                        $nuevos++;
                    }

                    // Pausa breve entre peticiones: uso considerado de la API
                    // (en re-ejecuciones el caché evita las peticiones HTTP)
                    usleep(100_000);
                }

                // Nota: synced_at e idiomas_sincronizados NO se tocan aquí — esas
                // marcas pertenecen al cacheo de cartas y deben sobrevivir a las
                // re-sincronizaciones del índice
                $set->save();
            }

            if (!$principal && $nuevos > 0) {
                $this->info("Serie \"{$serie->nombre}\": +{$nuevos} sets del catálogo \"{$idioma}\"");
            }

            // Cascada de logo de serie (3º eslabón): si sigue sin logo en ningún
            // idioma, hereda el del set más reciente que tenga; si ninguno
            // tiene, queda null → placeholder
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
