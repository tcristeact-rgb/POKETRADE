<?php

namespace Tests\Feature;

use App\Models\Serie;
use App\Models\Set;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// El caso que DestacadasTest no cubría: los sets más nuevos existen en TCGdex
// pero Cardmarket aún no les ha puesto precio. Antes, el hero recorría esos
// sets, gastaba sus intentos en cartas sin precio y devolvía [] — la portada de
// un clon limpio se quedaba sin hero aunque el README prometiera lo contrario.
class DestacadasSetsNuevosTest extends TestCase
{
    use RefreshDatabase;

    private const SETS_NUEVOS = ['nuevo1', 'nuevo2', 'nuevo3'];

    // Un TCGdex de mentira: tres sets recién salidos sin precio en ninguna
    // carta y un set maduro con precio en todas. Con o sin releaseDate en el
    // payload según el test.
    private function fakeTcgdex(bool $conReleaseDate, bool $maduroConPrecio = true): void
    {
        Http::fake(function (Request $request) use ($conReleaseDate, $maduroConPrecio) {
            $url = $request->url();

            if (preg_match('#/cards/([a-z0-9]+)-00(\d)$#', $url, $m)) {
                [$_, $set, $n] = $m;
                $nuevo = in_array($set, self::SETS_NUEVOS, true);

                return Http::response([
                    'id'      => "{$set}-00{$n}",
                    'localId' => "00{$n}",
                    'name'    => "Carta {$set} {$n}",
                    'image'   => "https://assets.tcgdex.net/en/x/{$set}/00{$n}",
                    'set'     => ['id' => $set],
                    'rarity'  => 'Rare',
                    'types'   => ['Fire'],
                    'pricing' => ($nuevo || !$maduroConPrecio) ? null : ['cardmarket' => ['avg' => 10 * (int) $n]],
                ]);
            }

            if (preg_match('#/sets/([a-z0-9]+)$#', $url, $m)) {
                $set   = $m[1];
                $nuevo = in_array($set, self::SETS_NUEVOS, true);

                return Http::response(array_filter([
                    'id'          => $set,
                    'name'        => "Set {$set}",
                    'releaseDate' => $conReleaseDate
                        ? ($nuevo ? now()->subWeeks(2)->toDateString() : now()->subWeeks(30)->toDateString())
                        : null,
                    'cards'       => array_map(fn ($n) => [
                        'id'      => "{$set}-00{$n}",
                        'localId' => "00{$n}",
                        'name'    => "Carta {$set} {$n}",
                        'image'   => "https://assets.tcgdex.net/en/x/{$set}/00{$n}",
                    ], range(1, 6)),
                ]));
            }

            return match (true) {
                str_ends_with($url, '/en/series') => Http::response([['id' => 'x', 'name' => 'Serie X']]),
                str_ends_with($url, '/en/series/x') => Http::response([
                    'id'   => 'x',
                    'name' => 'Serie X',
                    'sets' => [
                        ['id' => 'maduro', 'name' => 'Set maduro'],
                        ['id' => 'nuevo1', 'name' => 'Set nuevo1'],
                        ['id' => 'nuevo2', 'name' => 'Set nuevo2'],
                        ['id' => 'nuevo3', 'name' => 'Set nuevo3'],
                    ],
                ]),
                default => Http::response([], 404),
            };
        });
    }

    // El índice que deja tcgdex:sync-sets: los tres nuevos por delante del maduro
    private function crearIndice(): void
    {
        $serie = Serie::create(['tcgdex_id' => 'x', 'nombre' => 'Serie X']);

        Set::create(['tcgdex_id' => 'nuevo1', 'serie_id' => $serie->id, 'nombre' => 'Set nuevo1', 'fecha_lanzamiento' => now()->subWeeks(1)->toDateString()]);
        Set::create(['tcgdex_id' => 'nuevo2', 'serie_id' => $serie->id, 'nombre' => 'Set nuevo2', 'fecha_lanzamiento' => now()->subWeeks(4)->toDateString()]);
        Set::create(['tcgdex_id' => 'nuevo3', 'serie_id' => $serie->id, 'nombre' => 'Set nuevo3', 'fecha_lanzamiento' => now()->subWeeks(7)->toDateString()]);
        Set::create(['tcgdex_id' => 'maduro', 'serie_id' => $serie->id, 'nombre' => 'Set maduro', 'fecha_lanzamiento' => now()->subWeeks(30)->toDateString()]);
    }

    public function test_con_los_3_sets_mas_nuevos_sin_precio_devuelve_4_cartas(): void
    {
        $this->crearIndice();
        $this->fakeTcgdex(conReleaseDate: true);

        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertStatus(200)->assertJsonCount(4, 'data');

        // Las cuatro salen del set maduro, con precio, de la más cara abajo
        $this->assertSame(
            ['maduro-006', 'maduro-005', 'maduro-004', 'maduro-003'],
            array_column($res->json('data'), 'tcgdex_id')
        );
        $this->assertSame(60, (int) $res->json('data.0.precio_cardmarket'));

        // Los sets nuevos ni se piden: el índice ya dice que son demasiado recientes
        foreach (self::SETS_NUEVOS as $set) {
            Http::assertNotSent(fn (Request $r) => str_contains($r->url(), "/sets/{$set}"));
            Http::assertNotSent(fn (Request $r) => str_contains($r->url(), "/cards/{$set}-"));
        }
    }

    public function test_sin_indice_los_sets_nuevos_se_saltan_por_su_release_date(): void
    {
        // BD recién creada, sin sync-sets: el camino en vivo pide la última
        // serie a TCGdex y recorre sus sets del más nuevo al más viejo
        $this->fakeTcgdex(conReleaseDate: true);

        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertStatus(200)->assertJsonCount(4, 'data');
        $this->assertSame(
            ['maduro-006', 'maduro-005', 'maduro-004', 'maduro-003'],
            array_column($res->json('data'), 'tcgdex_id')
        );

        // El set nuevo se pide (es la única forma de ver su releaseDate) pero
        // ninguna de sus cartas se hidrata
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/sets/nuevo3'));
        foreach (self::SETS_NUEVOS as $set) {
            Http::assertNotSent(fn (Request $r) => str_contains($r->url(), "/cards/{$set}-"));
        }
        $this->assertDatabaseMissing('cartas', ['set_id' => 'nuevo3']);
    }

    public function test_sin_release_date_no_se_descarta_ningun_set(): void
    {
        // Un payload sin releaseDate no permite decidir: se recorre como siempre
        // (así DestacadasTest, cuyo TCGdex de mentira no manda fecha, no cambia)
        $this->fakeTcgdex(conReleaseDate: false);

        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertStatus(200)->assertJsonCount(4, 'data');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/cards/nuevo3-'));
    }

    public function test_si_ningun_set_tiene_precio_rellena_con_cartas_con_imagen_y_sin_precio(): void
    {
        // Cardmarket no lista nada de este catálogo: ni el set maduro tiene
        // precio. Antes: []. Ahora el hero enseña cartas sin precio, que el
        // frontend pinta sin la etiqueta de precio.
        $this->crearIndice();
        $this->fakeTcgdex(conReleaseDate: true, maduroConPrecio: false);

        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertStatus(200)->assertJsonCount(4, 'data');
        foreach ($res->json('data') as $carta) {
            $this->assertNull($carta['precio_cardmarket']);
            $this->assertNotNull($carta['imagen_low']);
            $this->assertIsInt($carta['id']);
        }
    }

    public function test_el_relleno_sin_precio_exige_imagen(): void
    {
        // Cartas sin precio y sin imagen (las manuales del admin) no valen para
        // el hero, que no tendría nada que enseñar
        $this->crearIndice();
        $this->conTcgdexCaido();
        \App\Models\Carta::insert(array_map(fn ($i) => [
            'nombre_es'  => "Sin imagen {$i}",
            'created_at' => now(),
            'updated_at' => now(),
        ], range(1, 6)));

        $this->getJson('/api/cartas/destacadas')
             ->assertStatus(200)
             ->assertExactJson(['data' => []]);
    }
}
