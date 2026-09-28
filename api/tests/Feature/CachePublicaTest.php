<?php

namespace Tests\Feature;

use App\Models\Carta;
use App\Models\Serie;
use App\Models\Set;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Qué respuestas puede guardar la CDN de Vercel y cuáles no (ver CachePublica),
// y que poner Vercel delante no mete a todos los visitantes en un mismo cupo del
// throttle
class CachePublicaTest extends TestCase
{
    use RefreshDatabase;

    // Un set ya cacheado en español con una carta ya hidratada: ninguna de las
    // rutas del catálogo necesita salir a TCGdex para responder 200
    private function crearCatalogo(): Carta
    {
        $serie = Serie::create(['tcgdex_id' => 'sv', 'nombre' => 'Escarlata y Púrpura']);

        Set::create([
            'tcgdex_id'             => 'sv03.5',
            'serie_id'              => $serie->id,
            'nombre'                => '151',
            'synced_at'             => now(),
            'idiomas_sincronizados' => ['es'],
        ]);

        return Carta::create([
            'nombre'            => 'Pikachu',
            'tcgdex_id'         => 'sv03.5-025',
            'set_id'            => 'sv03.5',
            'detalle_synced_at' => now(),
        ]);
    }

    private function assertCacheablePorLaCdn($respuesta, string $ruta): void
    {
        $respuesta->assertOk();

        $cacheControl = (string) $respuesta->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl, "{$ruta} no es public");
        $this->assertStringContainsString('s-maxage=3600', $cacheControl, "{$ruta} no lleva s-maxage=3600");
        $this->assertStringContainsString('stale-while-revalidate=86400', $cacheControl, "{$ruta} no lleva stale-while-revalidate");
        $this->assertStringContainsString('Accept-Language', (string) $respuesta->headers->get('Vary'), "{$ruta} no varía por idioma");
    }

    private function assertNoCacheablePorLaCdn($respuesta, string $ruta): void
    {
        $cacheControl = (string) $respuesta->headers->get('Cache-Control');
        $this->assertStringNotContainsString('public', $cacheControl, "{$ruta} no debería ser public");
        $this->assertStringNotContainsString('s-maxage', $cacheControl, "{$ruta} no debería llevar s-maxage");
    }

    public function test_las_rutas_publicas_del_catalogo_son_cacheables_por_la_cdn(): void
    {
        $carta = $this->crearCatalogo();

        // Lo que pregunten nombres, buscar o destacadas a TCGdex contesta vacío
        Http::fake(['api.tcgdex.net/*' => Http::response([])]);

        $rutas = [
            '/api/series',
            '/api/series/sv',
            '/api/sets',
            '/api/sets/sv03.5',
            '/api/sets/sv03.5/cartas',
            '/api/cartas',
            '/api/cartas/filtros',
            '/api/cartas/destacadas',
            '/api/cartas/nombres',
            '/api/cartas/buscar?q=pikachu',
            "/api/cartas/{$carta->id}",
        ];

        foreach ($rutas as $ruta) {
            $this->assertCacheablePorLaCdn($this->getJson($ruta), $ruta);
        }
    }

    public function test_el_navegador_guarda_un_minuto_salvo_que_el_controlador_diga_otra_cosa(): void
    {
        $this->crearCatalogo();
        Http::fake(['api.tcgdex.net/*' => Http::response([])]);

        $this->assertStringContainsString('max-age=60',
            $this->getJson('/api/sets')->headers->get('Cache-Control'));

        // /cartas/nombres ya fijaba 24 h en el navegador y las conserva
        $this->assertStringContainsString('max-age=86400',
            $this->getJson('/api/cartas/nombres')->headers->get('Cache-Control'));
    }

    public function test_lo_que_no_es_catalogo_publico_no_lo_guarda_la_cdn(): void
    {
        $this->crearCatalogo();

        $this->assertNoCacheablePorLaCdn($this->getJson('/api/health')->assertOk(), '/api/health');
        $this->assertNoCacheablePorLaCdn($this->getJson('/api/cartas/aleatorias')->assertOk(), '/api/cartas/aleatorias');
        $this->assertNoCacheablePorLaCdn($this->getJson('/api/tradeos')->assertOk(), '/api/tradeos');

        $usuario = User::create([
            'nombre'   => 'Test',
            'apellido' => 'Usuario',
            'email'    => 'cache@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'cliente',
        ]);
        $this->assertNoCacheablePorLaCdn(
            $this->actingAs($usuario, 'api')->getJson('/api/inventario')->assertOk(),
            '/api/inventario'
        );
    }

    public function test_un_404_no_lo_guarda_la_cdn(): void
    {
        $this->assertNoCacheablePorLaCdn(
            $this->getJson('/api/sets/no-existe')->assertNotFound(),
            '/api/sets/no-existe'
        );
    }

    public function test_un_503_de_tcgdex_caido_no_lo_guarda_la_cdn(): void
    {
        $serie = Serie::create(['tcgdex_id' => 'sv', 'nombre' => 'Escarlata y Púrpura']);
        Set::create(['tcgdex_id' => 'sv03.5', 'serie_id' => $serie->id, 'nombre' => '151']);

        Http::fake(['api.tcgdex.net/*' => Http::response(null, 500)]);

        $this->assertNoCacheablePorLaCdn(
            $this->getJson('/api/sets/sv03.5/cartas')->assertStatus(503),
            '/api/sets/sv03.5/cartas'
        );
    }

    // Detrás del rewrite de Vercel todas las peticiones llegan desde IPs de
    // Vercel. El throttle tiene que seguir contando por la IP real del cliente
    // (X-Forwarded-For), no por la del proxy: si no, todos los visitantes
    // compartirían un único cupo de 120/min
    public function test_el_throttle_cuenta_por_la_ip_real_del_cliente(): void
    {
        for ($i = 1; $i <= 120; $i++) {
            $this->withHeader('X-Forwarded-For', '1.1.1.1')->getJson('/api/health')->assertOk();
        }

        $this->withHeader('X-Forwarded-For', '1.1.1.1')->getJson('/api/health')->assertStatus(429);
        $this->withHeader('X-Forwarded-For', '2.2.2.2')->getJson('/api/health')->assertOk();
    }
}
