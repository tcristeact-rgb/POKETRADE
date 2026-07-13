<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase; // Resetea la BD entre cada test
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\Carta;
use App\Models\Serie;
use App\Models\Set;

class BusquedaGlobalTest extends TestCase
{
    // RefreshDatabase garantiza que cada test empieza con la BD limpia
    use RefreshDatabase;

    // Sin series excluidas por defecto: así los tests no dependen de la
    // config real ni disparan peticiones extra; el test de exclusión
    // activa la suya explícitamente
    protected function setUp(): void
    {
        parent::setUp();
        config(['tcgdex.series_excluidas' => []]);
    }

    // Resumen de carta tal y como lo devuelve /v2/{lang}/cards?...
    private function resumen(string $id, string $nombre, ?string $imagen = null): array
    {
        return array_filter([
            'id'      => $id,
            'localId' => substr($id, strpos($id, '-') + 1),
            'name'    => $nombre,
            'image'   => $imagen,
        ]);
    }

    // --- Test 1: Búsqueda global con resultados formateados ---
    // Los resúmenes de TCGdex salen con imagen_low/imagen_high montadas
    // y, si la carta ya está en BD, con su id interno para el enlace
    public function test_busqueda_global_devuelve_resultados_formateados()
    {
        $local = Carta::create(['nombre' => 'Charizard ex', 'tcgdex_id' => 'sv03.5-006']);

        Http::fake([
            'api.tcgdex.net/v2/es/cards?*' => Http::response([
                $this->resumen('sv03.5-006', 'Charizard ex', 'https://assets.tcgdex.net/es/sv/sv03.5/006'),
                $this->resumen('sm7.5-1', 'Charmander'), // sin imagen → null, nunca rota
            ]),
            'api.tcgdex.net/v2/en/cards?*' => Http::response([]),
        ]);

        $respuesta = $this->getJson('/api/cartas/buscar?q=char');

        $respuesta->assertStatus(200)
                  ->assertJsonPath('total', 2)
                  ->assertJsonPath('data.0.id', $local->id)
                  ->assertJsonPath('data.0.imagen_low', 'https://assets.tcgdex.net/es/sv/sv03.5/006/low.webp')
                  ->assertJsonPath('data.0.imagen_high', 'https://assets.tcgdex.net/es/sv/sv03.5/006/high.webp')
                  ->assertJsonPath('data.1.id', null)
                  ->assertJsonPath('data.1.tcgdex_id', 'sm7.5-1')
                  ->assertJsonPath('data.1.imagen_low', null);
    }

    // --- Test 2: El complemento inglés se deduplica por id ---
    public function test_busqueda_global_combina_espanol_e_ingles_sin_duplicar()
    {
        Http::fake([
            'api.tcgdex.net/v2/es/cards?*' => Http::response([
                $this->resumen('sv03.5-006', 'Charizard ex'),
            ]),
            'api.tcgdex.net/v2/en/cards?*' => Http::response([
                $this->resumen('sv03.5-006', 'Charizard ex'), // duplicada
                $this->resumen('base1-4', 'Charizard'),       // solo en inglés
            ]),
        ]);

        $respuesta = $this->getJson('/api/cartas/buscar?q=charizard');

        $respuesta->assertStatus(200)
                  ->assertJsonPath('total', 2)
                  ->assertJsonPath('data.1.tcgdex_id', 'base1-4');
    }

    // --- Test 2b: Las series excluidas se filtran de los resultados ---
    // TCGdex devuelve cartas de Pocket (set A1, sin prefijo de serie);
    // la config las excluye y no deben llegar al frontend
    public function test_busqueda_global_filtra_series_excluidas()
    {
        config(['tcgdex.series_excluidas' => ['tcgp']]);

        Http::fake([
            'api.tcgdex.net/v2/es/cards?*' => Http::response([
                $this->resumen('sv03.5-006', 'Charizard ex'),
                ['id' => 'A1-036', 'localId' => '036', 'name' => 'Charizard'], // Pocket
            ]),
            'api.tcgdex.net/v2/en/cards?*' => Http::response([]),
            'api.tcgdex.net/v2/en/series/tcgp' => Http::response([
                'id' => 'tcgp', 'name' => 'Pocket',
                'sets' => [['id' => 'A1', 'name' => 'Genetic Apex']],
            ]),
        ]);

        $respuesta = $this->getJson('/api/cartas/buscar?q=charizard');

        $respuesta->assertStatus(200)
                  ->assertJsonPath('total', 1)
                  ->assertJsonPath('data.0.tcgdex_id', 'sv03.5-006')
                  ->assertJsonMissing(['tcgdex_id' => 'A1-036']);
    }

    // --- Test 3: Sin ningún filtro → 422 ---
    public function test_busqueda_global_requiere_algun_filtro()
    {
        $this->getJson('/api/cartas/buscar')->assertStatus(422);
        $this->getJson('/api/cartas/buscar?q=c')->assertStatus(422); // menos de 2 caracteres
    }

    // --- Test 4: TCGdex caído → 503 ---
    public function test_busqueda_global_devuelve_503_si_tcgdex_no_responde()
    {
        Http::fake(['api.tcgdex.net/*' => Http::response(null, 500)]);

        $this->getJson('/api/cartas/buscar?q=charizard')
             ->assertStatus(503)
             ->assertJsonStructure(['error']);
    }

    // --- Test 5: Abrir un resultado global crea la fila bajo demanda ---
    // GET /api/cartas/{tcgdex_id} de una carta que no está en BD debe
    // crearla ya hidratada y responder con el detalle completo
    public function test_detalle_por_tcgdex_id_crea_la_fila_bajo_demanda()
    {
        $serie = Serie::create(['tcgdex_id' => 'sm', 'nombre' => 'Sol y Luna']);
        Set::create(['tcgdex_id' => 'sm7.5', 'serie_id' => $serie->id, 'nombre' => 'Dragones Majestuosos']);

        Http::fake(['api.tcgdex.net/v2/es/cards/sm7.5-1' => Http::response([
            'id'      => 'sm7.5-1',
            'localId' => '1',
            'name'    => 'Charmander',
            'types'   => ['Fuego'],
            'rarity'  => 'Común',
            'set'     => ['id' => 'sm7.5', 'name' => 'Dragones Majestuosos'],
            'image'   => 'https://assets.tcgdex.net/es/sm/sm7.5/1',
        ])]);

        $respuesta = $this->getJson('/api/cartas/sm7.5-1');

        $respuesta->assertStatus(200)
                  ->assertJsonFragment(['nombre' => 'Charmander'])
                  ->assertJsonFragment(['rareza' => 'Común'])
                  // El nombre del set ya no se copia en la carta: sale de la
                  // relación, y por eso llega traducido
                  ->assertJsonFragment(['set_expansion' => 'Dragones Majestuosos']);

        // Nace con el texto en la columna del catálogo del que salió, y con el
        // idioma anotado para no volver a pedir ese detalle
        $this->assertDatabaseHas('cartas', [
            'tcgdex_id' => 'sm7.5-1',
            'set_id'    => 'sm7.5',
            'nombre_es' => 'Charmander',
            'nombre_en' => null,
        ]);

        $carta = Carta::firstWhere('tcgdex_id', 'sm7.5-1');
        $this->assertNotNull($carta->detalle_synced_at);
        $this->assertTrue($carta->detalladoEn('es'));
    }

    // --- Test 6: tcgdex_id inexistente → 404 sin crear nada ---
    public function test_detalle_por_tcgdex_id_inexistente_devuelve_404()
    {
        Http::fake(['api.tcgdex.net/*' => Http::response(null, 404)]);

        $this->getJson('/api/cartas/noexiste-999')->assertStatus(404);
        $this->assertDatabaseCount('cartas', 0);
    }

    // --- Test 7: Filtro por tipo dentro de un set (intersección TCGdex) ---
    // El set está cacheado pero sin hidratar (tipo a NULL): el filtro
    // debe apoyarse en los IDs que devuelve TCGdex, no en SQL
    public function test_filtro_por_tipo_en_set_interseca_con_tcgdex()
    {
        $serie = Serie::create(['tcgdex_id' => 'sv', 'nombre' => 'Escarlata y Púrpura']);
        Set::create(['tcgdex_id' => 'sv03.5', 'serie_id' => $serie->id, 'nombre' => '151',
                     'synced_at' => now(), 'idiomas_sincronizados' => ['es']]);
        Carta::create(['nombre' => 'Charmander', 'tcgdex_id' => 'sv03.5-004', 'set_id' => 'sv03.5', 'numero' => '004']);
        Carta::create(['nombre' => 'Squirtle',   'tcgdex_id' => 'sv03.5-007', 'set_id' => 'sv03.5', 'numero' => '007']);

        Http::fake(['api.tcgdex.net/v2/*/cards?*' => Http::response([
            $this->resumen('sv03.5-004', 'Charmander'),
        ])]);

        $respuesta = $this->getJson('/api/sets/sv03.5/cartas?tipo=Fuego');

        $respuesta->assertStatus(200)
                  ->assertJsonCount(1, 'data')
                  ->assertJsonPath('data.0.tcgdex_id', 'sv03.5-004');
    }

    // --- Test 8: Filtro por nombre dentro de un set, solo SQL ---
    // Con el set cacheado y sin tipo/rareza no debe salir ni una
    // petición hacia TCGdex
    public function test_filtro_por_nombre_en_set_no_llama_a_tcgdex()
    {
        $serie = Serie::create(['tcgdex_id' => 'sv', 'nombre' => 'Escarlata y Púrpura']);
        Set::create(['tcgdex_id' => 'sv03.5', 'serie_id' => $serie->id, 'nombre' => '151',
                     'synced_at' => now(), 'idiomas_sincronizados' => ['es']]);
        Carta::create(['nombre' => 'Charmander', 'tcgdex_id' => 'sv03.5-004', 'set_id' => 'sv03.5']);
        Carta::create(['nombre' => 'Squirtle',   'tcgdex_id' => 'sv03.5-007', 'set_id' => 'sv03.5']);

        Http::fake();

        $respuesta = $this->getJson('/api/sets/sv03.5/cartas?nombre=char');

        $respuesta->assertStatus(200)
                  ->assertJsonCount(1, 'data')
                  ->assertJsonFragment(['nombre' => 'Charmander']);

        Http::assertNothingSent();
    }

    // --- Test 9: Los desplegables salen de TCGdex ---
    // Los tipos y rarezas ya NO vienen de TCGdex: son un conjunto cerrado y
    // salen de nuestro catálogo, ya traducidos. Así el desplegable de filtros
    // deja de depender de que una API de terceros responda.
    public function test_los_filtros_no_dependen_de_tcgdex()
    {
        Http::fake();   // cualquier petición saliente haría fallar el test

        $respuesta = $this->getJson('/api/cartas/filtros');

        $respuesta->assertStatus(200)
                  ->assertJsonCount(11, 'tipos')     // los 11 tipos del TCG
                  ->assertJsonCount(40, 'rarezas');

        Http::assertNothingSent();
    }

    // Cada opción es {clave, etiqueta}: la clave viaja en la URL y no depende
    // del idioma; la etiqueta es lo que lee la persona.
    public function test_los_filtros_llegan_traducidos_y_con_su_clave()
    {
        Http::fake();

        $es = $this->withHeader('Accept-Language', 'es')->getJson('/api/cartas/filtros');
        $es->assertJsonFragment(['clave' => 'fire', 'etiqueta' => 'Fuego']);

        // "Poco Común" es una MEJORA sobre TCGdex, cuyo catálogo español
        // devuelve esta rareza sin traducir ("Uncommon"), y son 92 de
        // nuestras cartas
        $es->assertJsonFragment(['clave' => 'uncommon', 'etiqueta' => 'Poco Común']);

        $en = $this->withHeader('Accept-Language', 'en')->getJson('/api/cartas/filtros');
        $en->assertJsonFragment(['clave' => 'fire', 'etiqueta' => 'Fire']);
        $en->assertJsonFragment(['clave' => 'uncommon', 'etiqueta' => 'Uncommon']);
    }
}
