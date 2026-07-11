<?php

namespace App\Services;

use App\Models\Carta;
use App\Models\Inventario;
use App\Models\Tradeo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// --- Purga de cartas con sus referencias ---
// Lógica compartida por cartas:purgar-legacy y tcgdex:purgar-excluidos:
// dado un conjunto de cartas a eliminar, localiza los inventarios que
// las contienen y los tradeos que las referencian (en cualquiera de los
// dos lados), y ejecuta el borrado transaccional. El informe (tablas,
// dry-run) es responsabilidad de cada comando.
class PurgaDeCartas
{
    // Entradas de inventario que contienen alguna de las cartas
    public function inventarios(Collection $cartaIds): Collection
    {
        return Inventario::whereIn('carta_id', $cartaIds)
            ->with(['usuario', 'carta'])
            ->get();
    }

    // Tradeos que referencian alguna de las cartas, ofrecida o buscada.
    // Se eliminan enteros, igual que hace TradeoController::destroy
    public function tradeos(Collection $cartaIds): Collection
    {
        $ids = DB::table('tradeo_cartas_ofrece')
            ->whereIn('carta_id', $cartaIds)->pluck('tradeo_id')
            ->merge(DB::table('tradeo_cartas_busca')
                ->whereIn('carta_id', $cartaIds)->pluck('tradeo_id'))
            ->unique();

        return Tradeo::whereIn('id', $ids)->with('usuario')->get();
    }

    // Borrado real, todo o nada: primero los tradeos afectados (detach
    // de pivotes + delete) y después las cartas, cuyo borrado cascada
    // sobre el inventario y los pivotes restantes (FK on delete cascade)
    public function ejecutar(Collection $cartaIds, Collection $tradeos): void
    {
        DB::transaction(function () use ($cartaIds, $tradeos) {
            foreach ($tradeos as $tradeo) {
                $tradeo->cartasOfrece()->detach();
                $tradeo->cartasBusca()->detach();
                $tradeo->delete();
            }

            Carta::whereIn('id', $cartaIds)->delete();
        });
    }
}
