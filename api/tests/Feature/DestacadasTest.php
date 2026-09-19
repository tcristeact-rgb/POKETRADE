<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase; // Resetea la BD entre cada test
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\Carta;
use App\Models\Serie;
use App\Models\Set;

class DestacadasTest extends TestCase
{
    // RefreshDatabase garantiza que cada test empieza con la BD limpia
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // El store array de la caché sobrevive entre tests del mismo
        // proceso; sin esto un test vería las destacadas de otro
        Cache::flush();
    }

    // Inserta $n cartas de relleno sin precio (engordan la ventana de
    // recientes igual que las cacheadas desde el resumen de un set).
    // insert() va directo a la BD sin pasar por el modelo, así que aquí hay
    // que nombrar la columna del idioma: no hay mutador que lo resuelva.
    private function crearRelleno(int $n): void
    {
        Carta::insert(array_map(fn ($i) => [
            'nombre_es'  => "Relleno {$i}",
            'created_at' => now(),
            'updated_at' => now(),
        ], range(1, $n)));
    }

    public function test_devuelve_las_4_mas_caras_de_las_200_recientes(): void
    {
        // Carta antigua carísima que queda FUERA de la ventana de 200
        Carta::create(['nombre' => 'Reliquia', 'precio_cardmarket' => 999]);
        $this->crearRelleno(196);

        // 5 recientes con precio dentro de la ventana
        foreach ([10, 40, 20, 30, 5] as $i => $precio) {
            Carta::create(['nombre' => "Reciente {$i}", 'precio_cardmarket' => $precio]);
        }

        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertStatus(200)->assertJsonCount(4, 'data');
        // Las 4 recientes más caras, de mayor a menor; la reliquia de
        // 999 no aparece porque ya salió de la ventana
        $this->assertSame(
            [40.0, 30.0, 20.0, 10.0],
            array_map('floatval', array_column($res->json('data'), 'precio_cardmarket'))
        );
    }

    public function test_fallback_completa_con_las_mas_caras_de_toda_la_bd(): void
    {
        // Dos antiguas caras (fuera de la ventana de 200)
        Carta::create(['nombre' => 'Antigua A', 'precio_cardmarket' => 500]);
        Carta::create(['nombre' => 'Antigua B', 'precio_cardmarket' => 400]);
        $this->crearRelleno(199);

        // Solo 2 recientes con precio: la ventana no llega a 4
        Carta::create(['nombre' => 'Reciente cara',   'precio_cardmarket' => 25]);
        Carta::create(['nombre' => 'Reciente barata', 'precio_cardmarket' => 8]);

        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertStatus(200)->assertJsonCount(4, 'data');
        // Primero las recientes (la novedad va delante), luego el
        // fallback global por precio
        $this->assertSame(
            ['Reciente cara', 'Reciente barata', 'Antigua A', 'Antigua B'],
            array_column($res->json('data'), 'nombre')
        );
    }

    public function test_devuelve_las_que_haya_si_no_llega_a_4_con_precio(): void
    {
        $this->conTcgdexCaido(); // sin fallback en vivo: solo lo que hay en la BD
        $this->crearRelleno(10);
        Carta::create(['nombre' => 'Única con precio', 'precio_cardmarket' => 12]);

        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('Única con precio', $res->json('data.0.nombre'));
    }

    public function test_incluye_los_campos_que_necesita_el_hero(): void
    {
        $this->conTcgdexCaido();
        // set_expansion ya no es una columna copiada en la carta: sale de la
        // relación con el set, que es donde vive el nombre de verdad
        $serie = Serie::create(['tcgdex_id' => 'sv', 'nombre' => 'Escarlata y Púrpura']);
        Set::create(['tcgdex_id' => 'sv03.5', 'serie_id' => $serie->id, 'nombre' => '151']);

        Carta::create([
            'nombre'            => 'Charizard ex',
            'set_id'            => 'sv03.5',
            'precio_cardmarket' => 416.53,
            'imagen_url'        => 'https://assets.tcgdex.net/es/sv/sv03.5/199',
        ]);

        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertStatus(200)
            ->assertJsonPath('data.0.nombre', 'Charizard ex')
            ->assertJsonPath('data.0.set_expansion', '151')
            ->assertJsonPath('data.0.imagen_low', 'https://assets.tcgdex.net/es/sv/sv03.5/199/low.webp')
            ->assertJsonPath('data.0.imagen_high', 'https://assets.tcgdex.net/es/sv/sv03.5/199/high.webp');
    }

    public function test_la_respuesta_se_cachea_una_hora(): void
    {
        $this->conTcgdexCaido();
        Carta::create(['nombre' => 'Primera', 'precio_cardmarket' => 10]);
        $this->getJson('/api/cartas/destacadas')->assertJsonCount(1, 'data');

        // Una carta nueva más cara NO altera la respuesta cacheada
        Carta::create(['nombre' => 'Nueva más cara', 'precio_cardmarket' => 100]);
        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Primera');

        // La clave lleva el idioma: cada uno cachea su propia respuesta y
        // el primer visitante no le fija el idioma a todos los demás
        $this->assertTrue(Cache::has('cartas.destacadas.es'));
    }

    public function test_la_cache_de_destacadas_es_independiente_por_idioma(): void
    {
        $this->conTcgdexCaido();
        Carta::create(['nombre' => 'Primera', 'precio_cardmarket' => 10]);

        $this->withHeader('Accept-Language', 'es')->getJson('/api/cartas/destacadas');

        // El inglés todavía no ha pasado por aquí: su hueco está vacío
        $this->assertTrue(Cache::has('cartas.destacadas.es'));
        $this->assertFalse(Cache::has('cartas.destacadas.en'));

        $this->withHeader('Accept-Language', 'en')->getJson('/api/cartas/destacadas');

        $this->assertTrue(Cache::has('cartas.destacadas.en'));
    }

    // ─── Respaldo en vivo desde TCGdex ───
    //
    // El catálogo ya no se siembra: una BD recién creada no tiene ni una
    // carta con precio, y el hero se completa desde TCGdex.

    // Un TCGdex de mentira: dos series (una excluida por config), la serie
    // "sv" con dos sets, y el set sv08 con seis cartas de las que la última
    // (006) no tiene precio. Lo que no conoce responde 404, que para
    // TcgdexService es "ese catálogo no lo tiene" ([]).
    private function respuestaTcgdex(Request $request)
    {
        $url = $request->url();

        if (preg_match('#/cards/(sv08-00(\d))$#', $url, $m)) {
            $n = (int) $m[2];

            return Http::response([
                'id'      => $m[1],
                'localId' => "00{$n}",
                'name'    => "Carta {$n}",
                'image'   => "https://assets.tcgdex.net/es/sv/sv08/00{$n}",
                'set'     => ['id' => 'sv08'],
                'rarity'  => 'Rare',
                'types'   => ['Fire'],
                'pricing' => $n === 6 ? null : ['cardmarket' => ['avg' => 10 * $n]],
            ]);
        }

        return match (true) {
            str_ends_with($url, '/en/series') => Http::response([
                ['id' => 'sv',   'name' => 'Scarlet & Violet'],
                ['id' => 'tcgp', 'name' => 'Pocket'],
            ]),
            str_ends_with($url, '/en/series/sv') => Http::response([
                'id'   => 'sv',
                'name' => 'Scarlet & Violet',
                'sets' => [['id' => 'sv07', 'name' => 'Stellar Crown'], ['id' => 'sv08', 'name' => 'Surging Sparks']],
            ]),
            str_ends_with($url, '/sets/sv08') => Http::response([
                'id'    => 'sv08',
                'name'  => 'Chispas Fulgurantes',
                'cards' => array_map(fn ($n) => [
                    'id'      => "sv08-00{$n}",
                    'localId' => "00{$n}",
                    'name'    => "Carta {$n}",
                    'image'   => "https://assets.tcgdex.net/es/sv/sv08/00{$n}",
                ], range(1, 6)),
            ]),
            default => Http::response([], 404),
        };
    }

    // El índice mínimo que deja tcgdex:sync-sets: una serie con un set
    private function crearIndice(): void
    {
        $serie = Serie::create(['tcgdex_id' => 'sv', 'nombre' => 'Escarlata y Púrpura']);
        Set::create(['tcgdex_id' => 'sv08', 'serie_id' => $serie->id, 'nombre' => 'Chispas Fulgurantes', 'fecha_lanzamiento' => '2024-11-08']);
    }

    public function test_con_la_bd_vacia_se_completa_desde_tcgdex(): void
    {
        Http::fake(fn (Request $r) => $this->respuestaTcgdex($r));

        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertStatus(200)->assertJsonCount(4, 'data');

        // Las cartas se toman desde el final del set (donde están las
        // caras) y la 006, sin precio, se salta
        $this->assertSame(
            ['sv08-005', 'sv08-004', 'sv08-003', 'sv08-002'],
            array_column($res->json('data'), 'tcgdex_id')
        );

        // Los mismos campos que consume el hero: id interno (enlaza al
        // detalle), nombre, imágenes montadas y precio
        $res->assertJsonPath('data.0.nombre', 'Carta 5')
            ->assertJsonPath('data.0.precio_cardmarket', 50)
            ->assertJsonPath('data.0.imagen_low', 'https://assets.tcgdex.net/es/sv/sv08/005/low.webp')
            ->assertJsonPath('data.0.imagen_high', 'https://assets.tcgdex.net/es/sv/sv08/005/high.webp');
        $this->assertIsInt($res->json('data.0.id'));

        // Persistidas por el mismo camino que abrir el detalle: la próxima
        // selección las encuentra en la BD
        $this->assertDatabaseHas('cartas', ['tcgdex_id' => 'sv08-005', 'set_id' => 'sv08', 'precio_cardmarket' => 50]);

        // Solo se hidratan las necesarias: la 001 ni se pide
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/cards/sv08-001'));

        // Sin índice en la BD, el set salió de la última serie no excluida
        // (tcgp, la última de la lista, es Pocket y está excluida)
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/en/series/sv'));
    }

    public function test_relleno_parcial_sin_duplicados(): void
    {
        $this->crearIndice();

        // Dos con precio en la BD: una manual y la 005 del set, ya hidratada
        Carta::create(['nombre' => 'Manual cara', 'precio_cardmarket' => 99]);
        Carta::create([
            'nombre'             => 'Carta 5',
            'tcgdex_id'          => 'sv08-005',
            'set_id'             => 'sv08',
            'precio_cardmarket'  => 50,
            'idiomas_detallados' => ['es'],
        ]);

        Http::fake(fn (Request $r) => $this->respuestaTcgdex($r));

        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertStatus(200)->assertJsonCount(4, 'data');

        // Primero las de la BD, luego el relleno; la 005 aparece UNA vez
        $this->assertSame(
            ['Manual cara', 'Carta 5', 'Carta 4', 'Carta 3'],
            array_column($res->json('data'), 'nombre')
        );
        $ids = array_filter(array_column($res->json('data'), 'tcgdex_id'));
        $this->assertSame($ids, array_unique($ids));

        // La 005 ya estaba: no se vuelve a pedir. Y con índice en la BD, la
        // lista de series de TCGdex no hace falta
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/cards/sv08-005'));
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/en/series'));
    }

    public function test_si_tcgdex_no_responde_devuelve_data_vacio_con_200(): void
    {
        $this->crearIndice();

        Http::fake(fn () => Http::response(null, 500));

        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertStatus(200)->assertExactJson(['data' => []]);
    }

    public function test_un_resultado_vacio_no_se_cachea(): void
    {
        $this->crearIndice();

        // TCGdex caído en la primera petición, recuperado en la segunda
        $caido = true;
        Http::fake(function (Request $r) use (&$caido) {
            return $caido ? Http::response(null, 500) : $this->respuestaTcgdex($r);
        });

        $this->getJson('/api/cartas/destacadas')->assertJsonCount(0, 'data');
        $this->assertFalse(Cache::has('cartas.destacadas.es'));

        $caido = false;
        $this->getJson('/api/cartas/destacadas')->assertJsonCount(4, 'data');
        $this->assertTrue(Cache::has('cartas.destacadas.es'));
    }
}
