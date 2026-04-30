<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Carta;

class CartasSeeder extends Seeder
{
    public function run(): void
    {
        $cartas = [
            ['nombre' => 'Charizard',  'numero' => '006', 'tipo' => 'Fuego',     'rareza' => 'Rara Holo',  'set_expansion' => 'Base Set',   'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/6.png'],
            ['nombre' => 'Pikachu',    'numero' => '025', 'tipo' => 'Eléctrico', 'rareza' => 'Común',      'set_expansion' => 'Base Set',   'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/25.png'],
            ['nombre' => 'Mewtwo',     'numero' => '150', 'tipo' => 'Psíquico',  'rareza' => 'Ultra Rara', 'set_expansion' => 'Base Set',   'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/150.png'],
            ['nombre' => 'Blastoise',  'numero' => '009', 'tipo' => 'Agua',      'rareza' => 'Rara Holo',  'set_expansion' => 'Base Set',   'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/9.png'],
            ['nombre' => 'Venusaur',   'numero' => '003', 'tipo' => 'Planta',    'rareza' => 'Rara Holo',  'set_expansion' => 'Base Set',   'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/3.png'],
            ['nombre' => 'Gengar',     'numero' => '094', 'tipo' => 'Fantasma',  'rareza' => 'Ultra Rara', 'set_expansion' => 'Fossil',     'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/94.png'],
            ['nombre' => 'Eevee',      'numero' => '133', 'tipo' => 'Normal',    'rareza' => 'Común',      'set_expansion' => 'Jungle',     'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/133.png'],
            ['nombre' => 'Snorlax',    'numero' => '143', 'tipo' => 'Normal',    'rareza' => 'Rara',       'set_expansion' => 'Base Set',   'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/143.png'],
            ['nombre' => 'Gyarados',   'numero' => '130', 'tipo' => 'Agua',      'rareza' => 'Ultra Rara', 'set_expansion' => 'Base Set',   'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/130.png'],
            ['nombre' => 'Dragonite',  'numero' => '149', 'tipo' => 'Dragón',    'rareza' => 'Rara Holo',  'set_expansion' => 'Fossil',     'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/149.png'],
            ['nombre' => 'Alakazam',   'numero' => '065', 'tipo' => 'Psíquico',  'rareza' => 'Rara Holo',  'set_expansion' => 'Base Set',   'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/65.png'],
            ['nombre' => 'Machamp',    'numero' => '068', 'tipo' => 'Lucha',     'rareza' => 'Rara Holo',  'set_expansion' => 'Base Set',   'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/68.png'],
            ['nombre' => 'Arcanine',   'numero' => '059', 'tipo' => 'Fuego',     'rareza' => 'Poco común', 'set_expansion' => 'Base Set',   'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/59.png'],
            ['nombre' => 'Lapras',     'numero' => '131', 'tipo' => 'Agua',      'rareza' => 'Rara',       'set_expansion' => 'Base Set',   'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/131.png'],
            ['nombre' => 'Vaporeon',   'numero' => '134', 'tipo' => 'Agua',      'rareza' => 'Poco común', 'set_expansion' => 'Jungle',     'imagen_url' => 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/134.png'],
        ];

        foreach ($cartas as $c) {
            Carta::create([
                'nombre'        => $c['nombre'],
                'numero'        => $c['numero'],
                'tipo'          => $c['tipo'],
                'rareza'        => $c['rareza'],
                'set_expansion' => $c['set_expansion'],
                'imagen_url'    => $c['imagen_url'],
                'descripcion'   => "Carta de {$c['nombre']} de la colección Pokémon TCG.",
            ]);
            echo "Insertada: {$c['nombre']}\n";
        }
    }
}