<?php

namespace App\Console\Commands;

use App\Models\Carta;
use App\Models\Inventario;
use App\Models\Tradeo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgarCartasLegacy extends Command
{
    // Nombre del comando para ejecutarlo desde la terminal
    // Uso: php artisan cartas:purgar-legacy          → solo informa (dry-run)
    //      php artisan cartas:purgar-legacy --force  → borra de verdad
    protected $signature = 'cartas:purgar-legacy
                            {--force : Ejecuta el borrado real; sin este flag solo se informa}';

    // Descripción que aparece al hacer php artisan list
    protected $description = 'Elimina las cartas legacy del seed antiguo de PokéAPI (las que no tienen tcgdex_id) junto con los inventarios y tradeos que las referencian. Dry-run por defecto.';

    public function handle()
    {
        // Criterio de legacy: toda carta del catálogo actual viene de
        // TCGdex y tiene tcgdex_id; las filas sin él son del seed viejo
        // de PokéAPI (o creadas a mano en pruebas antiguas)
        $cartas = Carta::whereNull('tcgdex_id')->orderBy('id')->get();

        if ($cartas->isEmpty()) {
            $this->info('No hay cartas legacy en la BD (todas tienen tcgdex_id). Nada que purgar.');
            return self::SUCCESS;
        }

        // Inventarios que referencian cartas legacy
        $inventarios = Inventario::whereIn('carta_id', $cartas->pluck('id'))
            ->with(['usuario', 'carta'])
            ->get();

        // Tradeos que referencian cartas legacy en cualquiera de los dos
        // lados (ofrecidas o buscadas): se eliminan enteros, igual que
        // hace TradeoController::destroy (detach de pivotes + delete)
        $tradeoIds = DB::table('tradeo_cartas_ofrece')
            ->whereIn('carta_id', $cartas->pluck('id'))->pluck('tradeo_id')
            ->merge(DB::table('tradeo_cartas_busca')
                ->whereIn('carta_id', $cartas->pluck('id'))->pluck('tradeo_id'))
            ->unique();

        $tradeos = Tradeo::whereIn('id', $tradeoIds)->with('usuario')->get();

        // ── Informe ────────────────────────────────────
        $this->info('Cartas legacy encontradas: ' . $cartas->count());
        $this->table(
            ['ID', 'Nombre', 'Imagen'],
            $cartas->map(fn ($c) => [$c->id, $c->nombre, mb_strimwidth($c->imagen_url ?? '—', 0, 60, '…')])
        );

        $this->info('Entradas de inventario afectadas: ' . $inventarios->count());
        if ($inventarios->isNotEmpty()) {
            $this->table(
                ['Usuario', 'Carta', 'Cantidad'],
                $inventarios->map(fn ($i) => [
                    ($i->usuario->email ?? "user #{$i->user_id}"),
                    $i->carta->nombre ?? "carta #{$i->carta_id}",
                    $i->cantidad,
                ])
            );
        }

        $this->info('Tradeos afectados (referencian alguna carta legacy): ' . $tradeos->count());
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

        // ── Borrado real, todo o nada ──────────────────
        DB::transaction(function () use ($cartas, $tradeos) {
            foreach ($tradeos as $tradeo) {
                $tradeo->cartasOfrece()->detach();
                $tradeo->cartasBusca()->detach();
                $tradeo->delete();
            }

            // El borrado de las cartas cascada sobre el inventario y los
            // pivotes restantes (FK on delete cascade)
            Carta::whereIn('id', $cartas->pluck('id'))->delete();
        });

        $this->info(sprintf(
            'Purga completada: %d cartas, %d entradas de inventario y %d tradeos eliminados.',
            $cartas->count(),
            $inventarios->count(),
            $tradeos->count()
        ));

        return self::SUCCESS;
    }
}
