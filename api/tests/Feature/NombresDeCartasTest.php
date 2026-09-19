<?php

namespace Tests\Feature;

use App\Models\Carta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// GET /api/cartas/nombres: el índice de nombres que el frontend baja una vez
// para autocompletar en memoria. Criterios de
// docs/specs/2026-09-19-autocompletado-indice-cliente.md.
class NombresDeCartasTest extends TestCase
{
    use RefreshDatabase;

    // Un catálogo de mentira: repetidos a propósito (Pikachu ×3), desordenados,
    // y el español con menos cartas y una que solo existe traducida
    private function fakeCatalogos(): void
    {
        Http::fake([
            'api.tcgdex.net/v2/en/cards' => Http::response([
                ['id' => 'sv08-001', 'name' => 'Pikachu'],
                ['id' => 'sv08-002', 'name' => 'Charizard ex'],
                ['id' => 'sv08-003', 'name' => 'Pikachu'],
                ['id' => 'base1-58', 'name' => 'Pikachu'],
                ['id' => 'sv08-004', 'name' => 'Bulbasaur'],
                ['id' => 'sv08-005'],                          // sin nombre: se ignora
            ]),
            'api.tcgdex.net/v2/es/cards' => Http::response([
                ['id' => 'sv08-001', 'name' => 'Pikachu'],
                ['id' => 'sv08-004', 'name' => 'Bulbasaur'],
                ['id' => 'sv08-006', 'name' => 'Squirtle de Teracristal'], // solo en español
            ]),
        ]);
    }

    public function test_devuelve_nombres_unicos_y_ordenados(): void
    {
        $this->fakeCatalogos();

        $res = $this->getJson('/api/cartas/nombres?idioma=en');

        $res->assertOk()->assertExactJson(['Bulbasaur', 'Charizard ex', 'Pikachu']);
    }

    public function test_en_espanol_une_los_nombres_espanoles_con_los_ingleses(): void
    {
        $this->fakeCatalogos();

        $res = $this->getJson('/api/cartas/nombres?idioma=es');

        // Los tres del catálogo español + los que solo están en inglés, sin repetir
        $res->assertOk()->assertExactJson(['Bulbasaur', 'Charizard ex', 'Pikachu', 'Squirtle de Teracristal']);
    }

    public function test_sin_parametro_usa_el_idioma_de_la_peticion(): void
    {
        $this->fakeCatalogos();

        $this->withHeader('Accept-Language', 'en')
             ->getJson('/api/cartas/nombres')
             ->assertOk()
             ->assertJsonMissing(['Squirtle de Teracristal']);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/v2/es/cards'));
    }

    public function test_idioma_no_soportado_devuelve_422(): void
    {
        $this->fakeCatalogos();

        $this->getJson('/api/cartas/nombres?idioma=xx')->assertStatus(422)->assertJsonStructure(['error']);

        Http::assertNothingSent();
    }

    public function test_es_cacheable_24_horas_en_el_navegador(): void
    {
        $this->fakeCatalogos();

        $res = $this->getJson('/api/cartas/nombres?idioma=en');

        $res->assertHeader('Cache-Control', 'max-age=86400, public');
        $this->assertNotEmpty($res->headers->get('ETag'));
    }

    public function test_la_segunda_peticion_no_vuelve_a_tcgdex(): void
    {
        $this->fakeCatalogos();

        $this->getJson('/api/cartas/nombres?idioma=en')->assertOk();
        $this->getJson('/api/cartas/nombres?idioma=en')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_con_tcgdex_caido_y_sin_copia_previa_devuelve_503(): void
    {
        $this->conTcgdexCaido();

        $this->getJson('/api/cartas/nombres?idioma=en')
             ->assertStatus(503)
             ->assertJsonStructure(['error']);
    }

    public function test_con_tcgdex_caido_sirve_la_ultima_lista_buena(): void
    {
        // Ayer se construyó la lista; hoy caduca y TCGdex no contesta
        Cache::forever('tcgdex:nombres:en:ultimo', ['Mew', 'Mewtwo']);
        $this->conTcgdexCaido();

        $this->getJson('/api/cartas/nombres?idioma=en')
             ->assertOk()
             ->assertExactJson(['Mew', 'Mewtwo']);
    }

    public function test_la_ruta_no_se_confunde_con_una_carta_llamada_nombres(): void
    {
        $this->fakeCatalogos();
        Carta::create(['nombre' => 'Trampa', 'tcgdex_id' => 'nombres']);

        // Si /cartas/nombres cayera en /cartas/{id}, devolvería la carta "Trampa"
        $this->getJson('/api/cartas/nombres?idioma=en')
             ->assertOk()
             ->assertExactJson(['Bulbasaur', 'Charizard ex', 'Pikachu']);
    }
}
