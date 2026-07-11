<?php

namespace App\Console\Commands;

use App\Models\Carta;
use App\Services\PurgaDeCartas;
use Illuminate\Console\Command;

class PurgarCartasLegacy extends Command
{
    // Nombre del comando para ejecutarlo desde la terminal
    // Uso: php artisan cartas:purgar-legacy          → solo informa (dry-run)
    //      php artisan cartas:purgar-legacy --force  → borra de verdad
    protected $signature = 'cartas:purgar-legacy
                            {--force : Ejecuta el borrado real; sin este flag solo se informa}';

    // Descripción que aparece al hacer php artisan list
    protected $description = 'Elimina las cartas legacy del seed antiguo de PokéAPI (las que no tienen tcgdex_id) junto con los inventarios y tradeos que las referencian. Dry-run por defecto.';

    public function handle(PurgaDeCartas $purga)
    {
        // Criterio de legacy: toda carta del catálogo actual viene de
        // TCGdex y tiene tcgdex_id; las filas sin él son del seed viejo
        // de PokéAPI (o creadas a mano en pruebas antiguas)
        $cartas = Carta::whereNull('tcgdex_id')->orderBy('id')->get();

        if ($cartas->isEmpty()) {
            $this->info('No hay cartas legacy en la BD (todas tienen tcgdex_id). Nada que purgar.');
            return self::SUCCESS;
        }

        // Inventarios y tradeos que referencian cartas legacy (lógica
        // compartida con tcgdex:purgar-excluidos)
        $inventarios = $purga->inventarios($cartas->pluck('id'));
        $tradeos     = $purga->tradeos($cartas->pluck('id'));

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
        $purga->ejecutar($cartas->pluck('id'), $tradeos);

        $this->info(sprintf(
            'Purga completada: %d cartas, %d entradas de inventario y %d tradeos eliminados.',
            $cartas->count(),
            $inventarios->count(),
            $tradeos->count()
        ));

        return self::SUCCESS;
    }
}
