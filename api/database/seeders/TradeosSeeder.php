<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tradeo;
use App\Models\Inventario;

class TradeosSeeder extends Seeder
{
    public function run(): void
    {
        // Los IDs corresponden a las primeras cartas del catálogo TCGdex
        // (set "151"), sembradas por CartasSeeder antes que este seeder

        // --- Tradeo 1: Admin ofrece Bulbasaur, busca Venusaur ex ---
        $tradeo1 = Tradeo::create([
            'user_id'     => 1,           // Admin
            'descripcion' => 'Ofrezco Bulbasaur en buen estado, busco Venusaur ex.',
            'estado'      => 'activo',    // El tradeo está disponible
        ]);
        $tradeo1->cartasOfrece()->attach(1); // Bulbasaur (id: 1)
        $tradeo1->cartasBusca()->attach(3);  // Venusaur ex (id: 3)
        $this->retirarDelInventario(1, [1]);

        // --- Tradeo 2: Teo ofrece Charizard ex y Blastoise ex, busca Charmander ---
        $tradeo2 = Tradeo::create([
            'user_id'     => 2,           // Teo
            'descripcion' => 'Tengo Charizard ex y Blastoise ex, me interesa Charmander.',
            'estado'      => 'activo',
        ]);
        $tradeo2->cartasOfrece()->attach([6, 9]); // Charizard ex (id: 6) y Blastoise ex (id: 9)
        $tradeo2->cartasBusca()->attach(4);       // Charmander (id: 4)
        $this->retirarDelInventario(2, [6, 9]);

        // --- Tradeo 3: María ofrece Caterpie, busca Squirtle y Beedrill ---
        $tradeo3 = Tradeo::create([
            'user_id'     => 3,           // María
            'descripcion' => 'Cambio Caterpie por Squirtle o Beedrill.',
            'estado'      => 'activo',
        ]);
        $tradeo3->cartasOfrece()->attach(10);     // Caterpie (id: 10)
        $tradeo3->cartasBusca()->attach([7, 15]); // Squirtle (id: 7) y Beedrill (id: 15)
        $this->retirarDelInventario(3, [10]);

        // --- Tradeo 4: Admin, ya cerrado (ejemplo de historial) ---
        $tradeo4 = Tradeo::create([
            'user_id'     => 1,           // Admin
            'descripcion' => 'Tradeo ya completado.',
            'estado'      => 'cerrado',   // Este tradeo ya no está activo
        ]);
        $tradeo4->cartasOfrece()->attach(2); // Ivysaur (id: 2)
        $tradeo4->cartasBusca()->attach(6);  // Charizard ex (id: 6)
        $this->retirarDelInventario(1, [2]);
    }

    // Retira del inventario del creador las cartas que ofrece en un tradeo,
    // descontando por cantidad igual que hace TradeoController::store().
    // Mantiene los datos semilla coherentes con la lógica de publicación
    // (sin esto, las cartas ofrecidas quedarían duplicadas en el inventario).
    private function retirarDelInventario(int $userId, array $cartaIds): void
    {
        foreach ($cartaIds as $cartaId) {
            $entrada = Inventario::where('user_id', $userId)
                ->where('carta_id', $cartaId)
                ->first();

            if (!$entrada) {
                continue;
            }

            if ($entrada->cantidad > 1) {
                $entrada->decrement('cantidad');
            } else {
                $entrada->delete();
            }
        }
    }
}
