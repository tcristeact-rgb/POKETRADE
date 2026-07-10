<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase; // Resetea la BD entre cada test
use Tests\TestCase;
use App\Models\Serie;
use App\Models\Set;

class ExpansionesTest extends TestCase
{
    // RefreshDatabase garantiza que cada test empieza con la BD limpia
    use RefreshDatabase;

    // Crea una serie con sus sets directamente en la BD (sin pasar por
    // TCGdex): aquí probamos los endpoints del índice, no el comando
    private function crearSerie(string $tcgdexId, string $nombre, array $sets = []): Serie
    {
        $serie = Serie::create(['tcgdex_id' => $tcgdexId, 'nombre' => $nombre]);

        foreach ($sets as $set) {
            Set::create($set + ['serie_id' => $serie->id]);
        }

        return $serie;
    }

    // --- Test 1: Listado público de series ---
    // Comprueba que cualquiera (sin login) ve las series con su nº de sets
    // y ordenadas por la fecha de su set más reciente (nuevas primero)
    public function test_cualquiera_puede_ver_series_ordenadas()
    {
        $this->crearSerie('swsh', 'Espada y Escudo', [
            ['tcgdex_id' => 'swsh12.5', 'nombre' => 'Cénit Supremo', 'fecha_lanzamiento' => '2023-01-20'],
        ]);
        $this->crearSerie('sv', 'Escarlata y Púrpura', [
            ['tcgdex_id' => 'sv03.5', 'nombre' => '151', 'fecha_lanzamiento' => '2023-09-22'],
        ]);

        $respuesta = $this->getJson('/api/series');

        // La serie con el set más nuevo (sv) debe ir primero
        $respuesta->assertStatus(200)
                  ->assertJsonCount(2)
                  ->assertJsonPath('0.tcgdex_id', 'sv')
                  ->assertJsonPath('0.sets_count', 1)
                  ->assertJsonPath('1.tcgdex_id', 'swsh');
    }

    // --- Test 2: Detalle de serie con sets ordenados ---
    // Comprueba que la serie se resuelve por su ID de TCGdex y que sus
    // sets vienen de más reciente a más antiguo, con los sin fecha al final
    public function test_detalle_de_serie_incluye_sets_ordenados()
    {
        $this->crearSerie('sv', 'Escarlata y Púrpura', [
            ['tcgdex_id' => 'sv01',   'nombre' => 'Escarlata y Púrpura', 'fecha_lanzamiento' => '2023-03-31'],
            ['tcgdex_id' => 'svp',    'nombre' => 'Promos SVP',          'fecha_lanzamiento' => null],
            ['tcgdex_id' => 'sv03.5', 'nombre' => '151',                 'fecha_lanzamiento' => '2023-09-22'],
        ]);

        $respuesta = $this->getJson('/api/series/sv');

        $respuesta->assertStatus(200)
                  ->assertJsonPath('nombre', 'Escarlata y Púrpura')
                  ->assertJsonPath('sets.0.tcgdex_id', 'sv03.5')
                  ->assertJsonPath('sets.1.tcgdex_id', 'sv01')
                  ->assertJsonPath('sets.2.tcgdex_id', 'svp');
    }

    // --- Test 3: Serie no encontrada ---
    public function test_serie_no_encontrada_devuelve_404()
    {
        $respuesta = $this->getJson('/api/series/inexistente');

        $respuesta->assertStatus(404);
    }

    // --- Test 4: Filtrar sets por serie ---
    // Comprueba que ?serie= devuelve solo los sets de esa serie
    public function test_se_puede_filtrar_sets_por_serie()
    {
        $this->crearSerie('sv', 'Escarlata y Púrpura', [
            ['tcgdex_id' => 'sv03.5', 'nombre' => '151', 'fecha_lanzamiento' => '2023-09-22'],
        ]);
        $this->crearSerie('swsh', 'Espada y Escudo', [
            ['tcgdex_id' => 'swsh12.5', 'nombre' => 'Cénit Supremo', 'fecha_lanzamiento' => '2023-01-20'],
        ]);

        $respuesta = $this->getJson('/api/sets?serie=sv');

        $respuesta->assertStatus(200)
                  ->assertJsonCount(1)
                  ->assertJsonFragment(['tcgdex_id' => 'sv03.5']);
    }

    // --- Test 5: Detalle de set por ID de TCGdex ---
    // Comprueba también que el logo sale como URL completa .webp
    // (TCGdex sirve los assets sin extensión)
    public function test_detalle_de_set_por_id_tcgdex()
    {
        $this->crearSerie('sv', 'Escarlata y Púrpura', [
            [
                'tcgdex_id'     => 'sv03.5',
                'nombre'        => '151',
                'logo_url'      => 'https://assets.tcgdex.net/es/sv/sv03.5/logo',
                'numero_cartas' => 207,
            ],
        ]);

        $respuesta = $this->getJson('/api/sets/sv03.5');

        $respuesta->assertStatus(200)
                  ->assertJsonPath('nombre', '151')
                  ->assertJsonPath('numero_cartas', 207)
                  ->assertJsonPath('logo', 'https://assets.tcgdex.net/es/sv/sv03.5/logo.webp')
                  ->assertJsonPath('serie.tcgdex_id', 'sv');
    }

    // --- Test 6: Set no encontrado ---
    public function test_set_no_encontrado_devuelve_404()
    {
        $respuesta = $this->getJson('/api/sets/inexistente');

        $respuesta->assertStatus(404);
    }
}
