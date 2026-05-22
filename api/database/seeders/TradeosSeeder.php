<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tradeo;
use App\Models\Inventario;

class TradeosSeeder extends Seeder
{
    public function run(): void
    {
        // --- Tradeo 1: Admin ofrece Charizard, busca Mewtwo ---
        $tradeo1 = Tradeo::create([
            'user_id'     => 1,           // Admin
            'descripcion' => 'Ofrezco Charizard en buen estado, busco Mewtwo.',
            'estado'      => 'activo',    // El tradeo está disponible
        ]);
        $tradeo1->cartasOfrece()->attach(1); // Charizard (id: 1)
        $tradeo1->cartasBusca()->attach(3);  // Mewtwo (id: 3)
        $this->retirarDelInventario(1, [1]);

        // --- Tradeo 2: Teo ofrece Gengar y Gyarados, busca Blastoise ---
        $tradeo2 = Tradeo::create([
            'user_id'     => 2,           // Teo
            'descripcion' => 'Tengo Gengar y Gyarados, me interesa Blastoise.',
            'estado'      => 'activo',
        ]);
        $tradeo2->cartasOfrece()->attach([6, 9]); // Gengar (id: 6) y Gyarados (id: 9)
        $tradeo2->cartasBusca()->attach(4);       // Blastoise (id: 4)
        $this->retirarDelInventario(2, [6, 9]);

        // --- Tradeo 3: María ofrece Dragonite, busca Eevee y Vaporeon ---
        $tradeo3 = Tradeo::create([
            'user_id'     => 3,           // María
            'descripcion' => 'Cambio Dragonite por Eevee o Vaporeon.',
            'estado'      => 'activo',
        ]);
        $tradeo3->cartasOfrece()->attach(10);     // Dragonite (id: 10)
        $tradeo3->cartasBusca()->attach([7, 15]); // Eevee (id: 7) y Vaporeon (id: 15)
        $this->retirarDelInventario(3, [10]);

        // --- Tradeo 4: Admin, ya cerrado (ejemplo de historial) ---
        $tradeo4 = Tradeo::create([
            'user_id'     => 1,           // Admin
            'descripcion' => 'Tradeo ya completado.',
            'estado'      => 'cerrado',   // Este tradeo ya no está activo
        ]);
        $tradeo4->cartasOfrece()->attach(2); // Pikachu (id: 2)
        $tradeo4->cartasBusca()->attach(6);  // Gengar (id: 6)
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
