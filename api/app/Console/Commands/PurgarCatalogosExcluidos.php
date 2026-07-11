<?php

namespace App\Console\Commands;

use App\Models\Carta;
use App\Models\Serie;
use App\Models\Set;
use App\Services\PurgaDeCartas;
use App\Services\TcgdexService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgarCatalogosExcluidos extends Command
{
    // Nombre del comando para ejecutarlo desde la terminal
    // Uso: php artisan tcgdex:purgar-excluidos          → solo informa (dry-run)
    //      php artisan tcgdex:purgar-excluidos --force  → borra de verdad
    protected $signature = 'tcgdex:purgar-excluidos
                            {--force : Ejecuta el borrado real; sin este flag solo se informa}';

    // Descripción que aparece al hacer php artisan list
    protected $description = 'Elimina de la BD las series excluidas por config (tcgdex.series_excluidas) con sus sets, cartas cacheadas, inventarios y tradeos que las referencian. Dry-run por defecto.';

    public function handle(TcgdexService $tcgdex, PurgaDeCartas $purga)
    {
        $excluidas = config('tcgdex.series_excluidas', []);

        if (empty($excluidas)) {
            $this->info('No hay series excluidas en config/tcgdex.php. Nada que purgar.');
            return self::SUCCESS;
        }

        $this->info('Series excluidas por config: ' . implode(', ', $excluidas));

        // Series y sets presentes en la BD
        $series = Serie::whereIn('tcgdex_id', $excluidas)->withCount('sets')->get();

        // IDs de set afectados: los que hay en BD ∪ los que declara
        // TCGdex para esas series (por si quedaron cartas cacheadas de
        // un set cuyo índice ya no existe en la BD)
        $setIds = Set::whereIn('serie_id', $series->pluck('id'))->pluck('tcgdex_id')
            ->merge($tcgdex->setsExcluidos())
            ->unique()
            ->values();

        // Cartas cacheadas de esos sets, con sus referencias
        $cartas      = Carta::whereIn('set_id', $setIds)->get();
        $inventarios = $purga->inventarios($cartas->pluck('id'));
        $tradeos     = $purga->tradeos($cartas->pluck('id'));

        if ($series->isEmpty() && $cartas->isEmpty()) {
            $this->info('La BD no contiene nada de las series excluidas. Nada que purgar.');
            return self::SUCCESS;
        }

        // ── Informe ────────────────────────────────────
        $this->info('Series en BD a eliminar: ' . $series->count() . ' (sus sets caen en cascada)');
        if ($series->isNotEmpty()) {
            $this->table(
                ['ID TCGdex', 'Nombre', 'Sets'],
                $series->map(fn ($s) => [$s->tcgdex_id, $s->nombre, $s->sets_count])
            );
        }

        $this->info('Cartas cacheadas a eliminar: ' . $cartas->count());

        $this->info('Entradas de inventario afectadas: ' . $inventarios->count());
        if ($inventarios->isNotEmpty()) {
            $this->table(
                ['Usuario', 'Carta', 'Cantidad'],
                $inventarios->map(fn ($i) => [
                    $i->usuario->email ?? "user #{$i->user_id}",
                    $i->carta->nombre ?? "carta #{$i->carta_id}",
                    $i->cantidad,
                ])
            );
        }

        $this->info('Tradeos afectados (referencian alguna carta excluida): ' . $tradeos->count());
        if ($tradeos->isNotEmpty()) {
            $this->table(
                ['ID', 'Estado', 'Usuario'],
                $tradeos->map(fn ($t) => [$t->id, $t->estado, $t->usuario->email ?? "user #{$t->user_id}"])
            );
        }

        // ── Dry-run: aquí se acaba ─────────────────────
        if (!$this->option('force')) {
            $this->warn('Dry-run: NO se ha borrado nada. Ejecuta con --force para aplicar el borrado.');
            return self::SUCCESS;
        }

        // ── Borrado real ───────────────────────────────
        // Cartas + tradeos con la lógica compartida; después las series,
        // cuyo borrado cascada sobre sus sets (FK on delete cascade)
        $purga->ejecutar($cartas->pluck('id'), $tradeos);
        DB::transaction(fn () => Serie::whereIn('id', $series->pluck('id'))->delete());

        $this->info(sprintf(
            'Purga completada: %d series, %d cartas, %d entradas de inventario y %d tradeos eliminados.',
            $series->count(),
            $cartas->count(),
            $inventarios->count(),
            $tradeos->count()
        ));

        return self::SUCCESS;
    }
}
