<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Tradeo;

class TradeosSeeder extends Seeder
{
    public function run(): void
    {
        // --- Tradeo 1: Daniel ofrece Charizard, busca Mewtwo ---
        $tradeo1 = Tradeo::create([
            'user_id'     => 1,           // Daniel
            'descripcion' => 'Ofrezco Charizard en buen estado, busco Mewtwo.',
            'estado'      => 'activo',    // El tradeo está disponible
        ]);

        // Cartas que Daniel ofrece en este tradeo
        $tradeo1->cartasOfrece()->attach(1); // Charizard (id: 1)

        // Cartas que Daniel busca a cambio
        $tradeo1->cartasBusca()->attach(3);  // Mewtwo (id: 3)

        // --- Tradeo 2: Teo ofrece Gengar y Gyarados, busca Blastoise ---
        $tradeo2 = Tradeo::create([
            'user_id'     => 2,           // Teo
            'descripcion' => 'Tengo Gengar y Gyarados, me interesa Blastoise.',
            'estado'      => 'activo',
        ]);

        // Cartas que Teo ofrece (puede ser más de una)
        $tradeo2->cartasOfrece()->attach([6, 9]); // Gengar (id: 6) y Gyarados (id: 9)

        // Cartas que Teo busca
        $tradeo2->cartasBusca()->attach(4);  // Blastoise (id: 4)

        // --- Tradeo 3: María ofrece Dragonite, busca Eevee y Vaporeon ---
        $tradeo3 = Tradeo::create([
            'user_id'     => 3,           // María
            'descripcion' => 'Cambio Dragonite por Eevee o Vaporeon.',
            'estado'      => 'activo',
        ]);

        // Cartas que María ofrece
        $tradeo3->cartasOfrece()->attach(10); // Dragonite (id: 10)

        // Cartas que María busca (puede ser más de una)
        $tradeo3->cartasBusca()->attach([7, 15]); // Eevee (id: 7) y Vaporeon (id: 15)

        // --- Tradeo 4: Daniel, ya cerrado (ejemplo de historial) ---
        $tradeo4 = Tradeo::create([
            'user_id'     => 1,           // Daniel
            'descripcion' => 'Tradeo ya completado.',
            'estado'      => 'cerrado',   // Este tradeo ya no está activo
        ]);

        $tradeo4->cartasOfrece()->attach(2); // Pikachu (id: 2)
        $tradeo4->cartasBusca()->attach(6);  // Gengar (id: 6)
    }
}