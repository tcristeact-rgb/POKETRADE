<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase; // Resetea la BD entre cada test
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\Carta;
use App\Models\Serie;
use App\Models\Set;

class CacheAsideTest extends TestCase
{
    // RefreshDatabase garantiza que cada test empieza con la BD limpia
    use RefreshDatabase;

    // Crea el índice mínimo: una serie con un set (sin cartas cacheadas)
    private function crearSet(array $atributos = []): Set
    {
        $serie = Serie::create(['tcgdex_id' => 'sv', 'nombre' => 'Escarlata y Púrpura']);

        return Set::create($atributos + [
            'tcgdex_id' => 'sv03.5',
            'serie_id'  => $serie->id,
            'nombre'    => '151',
        ]);
    }

    // Respuesta simulada de GET /v2/{lang}/sets/sv03.5 con dos cartas
    private function respuestaSetTcgdex(): array
    {
        return [
            'id'    => 'sv03.5',
            'name'  => '151',
            'cards' => [
                ['id' => 'sv03.5-001', 'localId' => '001', 'name' => 'Bulbasaur', 'image' => 'https://assets.tcgdex.net/es/sv/sv03.5/001'],
                ['id' => 'sv03.5-025', 'localId' => '025', 'name' => 'Pikachu',   'image' => 'https://assets.tcgdex.net/es/sv/sv03.5/025'],
            ],
        ];
    }

    // --- Test 1: Primera visita a un set → se cachea desde TCGdex ---
    // Comprueba el cache-aside completo: descarga, persistencia en la
    // tabla cartas, marca synced_at y respuesta paginada
    public function test_primera_visita_cachea_las_cartas_del_set()
    {
        $set = $this->crearSet();

        Http::fake(['api.tcgdex.net/*' => Http::response($this->respuestaSetTcgdex())]);

        $respuesta = $this->getJson('/api/sets/sv03.5/cartas');

        // Responde el paginador de Laravel con las 2 cartas del set
        $respuesta->assertStatus(200)
                  ->assertJsonCount(2, 'data')
                  ->assertJsonPath('data.0.nombre', 'Bulbasaur');

        // Las cartas quedaron persistidas y el set marcado como sincronizado
        $this->assertDatabaseHas('cartas', ['tcgdex_id' => 'sv03.5-025', 'set_id' => 'sv03.5']);
        $this->assertNotNull($set->fresh()->synced_at);
    }

    // --- Test 2: Set ya cacheado → se sirve desde la BD ---
    // Comprueba que un set con synced_at no vuelve a llamar a TCGdex
    public function test_set_cacheado_se_sirve_sin_llamar_a_tcgdex()
    {
        $set = $this->crearSet(['synced_at' => now()]);
        Carta::create(['nombre' => 'Pikachu', 'tcgdex_id' => 'sv03.5-025', 'set_id' => 'sv03.5']);

        Http::fake();

        $respuesta = $this->getJson('/api/sets/sv03.5/cartas');

        $respuesta->assertStatus(200)
                  ->assertJsonCount(1, 'data');

        // Ni una sola petición HTTP a la API externa
        Http::assertNothingSent();
    }

    // --- Test 2b: Catálogo español incompleto → se cachea desde el inglés ---
    // Algunos sets existen en "es" solo como metadatos, con la lista de
    // cartas vacía (p. ej. neo1): el servicio debe caer al catálogo "en"
    public function test_set_sin_cartas_en_espanol_se_cachea_desde_el_ingles()
    {
        $this->crearSet();

        Http::fake([
            'api.tcgdex.net/v2/es/*' => Http::response([
                'id'        => 'sv03.5',
                'name'      => '151',
                'cardCount' => ['total' => 2],
                'cards'     => [], // metadatos sin cartas, como neo1 real
            ]),
            'api.tcgdex.net/v2/en/*' => Http::response($this->respuestaSetTcgdex()),
        ]);

        $respuesta = $this->getJson('/api/sets/sv03.5/cartas');

        $respuesta->assertStatus(200)
                  ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('cartas', ['tcgdex_id' => 'sv03.5-025']);
    }

    // --- Test 3: TCGdex caído → 503 y el set queda sin marcar ---
    // Comprueba que el fallo externo no deja el set a medio cachear
    public function test_si_tcgdex_no_responde_devuelve_503()
    {
        $set = $this->crearSet();

        Http::fake(['api.tcgdex.net/*' => Http::response(null, 500)]);

        $respuesta = $this->getJson('/api/sets/sv03.5/cartas');

        $respuesta->assertStatus(503)
                  ->assertJsonStructure(['error']);

        // Nada persistido: el siguiente intento volverá a preguntar
        $this->assertNull($set->fresh()->synced_at);
        $this->assertDatabaseCount('cartas', 0);
    }

    // --- Test 4: El cacheo no pisa el detalle ya existente ---
    // Una carta seedeada con detalle completo debe conservar su rareza y
    // precio cuando su set se cachea desde el resumen (que no los trae)
    public function test_cachear_un_set_conserva_el_detalle_existente()
    {
        $this->crearSet();
        Carta::create([
            'nombre'            => 'Pikachu',
            'tcgdex_id'         => 'sv03.5-025',
            'set_id'            => 'sv03.5',
            'rareza_key'        => 'common',
            'precio_cardmarket' => 3.50,
        ]);

        Http::fake(['api.tcgdex.net/*' => Http::response($this->respuestaSetTcgdex())]);

        $this->getJson('/api/sets/sv03.5/cartas')->assertStatus(200);

        $carta = Carta::where('tcgdex_id', 'sv03.5-025')->first();
        $this->assertSame('Común', $carta->rareza);
        $this->assertSame(3.50, $carta->precio_cardmarket);
    }

    // --- Test 5: Hidratación perezosa del detalle de una carta ---
    // Una carta cacheada desde el resumen (sin rareza ni precio) se
    // completa desde TCGdex la primera vez que se abre su detalle
    public function test_abrir_una_carta_hidrata_su_detalle()
    {
        $carta = Carta::create([
            'nombre'    => 'Pikachu',
            'tcgdex_id' => 'sv03.5-025',
            'set_id'    => 'sv03.5',
        ]);

        Http::fake(['api.tcgdex.net/*' => Http::response([
            'id'          => 'sv03.5-025',
            'name'        => 'Pikachu',
            'localId'     => '025',
            'types'       => ['Eléctrico'],
            'rarity'      => 'Común',
            'illustrator' => 'Atsuko Nishida',
            'hp'          => 60,
            'image'       => 'https://assets.tcgdex.net/es/sv/sv03.5/025',
            'pricing'     => ['cardmarket' => ['avg' => 3.5]],
        ])]);

        $respuesta = $this->getJson("/api/cartas/{$carta->id}");

        // La respuesta ya incluye el detalle completo...
        $respuesta->assertStatus(200)
                  ->assertJsonFragment(['rareza' => 'Común'])
                  ->assertJsonFragment(['hp' => 60]);

        // ...y quedó persistido con su marca de hidratación
        $carta->refresh();
        $this->assertSame('Común', $carta->rareza);
        $this->assertNotNull($carta->detalle_synced_at);
    }

    // --- Test 6: Si la hidratación falla, la carta se sirve igual ---
    // TCGdex caído no rompe el detalle: se responde lo que hay en la BD
    // y la marca queda a null para reintentar en la próxima visita
    public function test_detalle_se_sirve_aunque_la_hidratacion_falle()
    {
        $carta = Carta::create([
            'nombre'    => 'Pikachu',
            'tcgdex_id' => 'sv03.5-025',
            'set_id'    => 'sv03.5',
        ]);

        Http::fake(['api.tcgdex.net/*' => Http::response(null, 500)]);

        $respuesta = $this->getJson("/api/cartas/{$carta->id}");

        $respuesta->assertStatus(200)
                  ->assertJsonFragment(['nombre' => 'Pikachu']);

        $this->assertNull($carta->fresh()->detalle_synced_at);
    }
}
