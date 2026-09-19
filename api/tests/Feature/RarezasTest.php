<?php

namespace Tests\Feature;

use App\Models\Carta;
use App\Support\Rarezas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// --- Taxonomía cerrada de rarezas (spec 2026-09-19-taxonomia-de-rarezas) ---
//
// cartas.rareza_key guarda la clave fina de TCGdex; lo que se enseña y por
// lo que se filtra es una de diez categorías, con orden y símbolo. La tabla
// que lo decide está en App\Support\Rarezas y aquí se fija con datos reales.
class RarezasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tcgdex.series_excluidas' => []]);
    }

    // Las 15 claves que había de verdad en la BD el día de la spec
    // (SELECT rareza_key, COUNT(*) … GROUP BY 1), más las que TCGdex sirve
    // y aún no teníamos. Si alguien mueve una fila de MAPA, este test lo dice.
    public function test_el_mapeo_de_las_rarezas_reales_del_inventario(): void
    {
        $esperado = [
            'common'                    => 'comun',
            'uncommon'                  => 'infrecuente',
            'rare'                      => 'rara',
            'holo-rare'                 => 'rara',
            'holo-rare-v'               => 'rara',
            'holo-rare-vstar'           => 'rara',
            'holo-rare-vmax'            => 'rara',
            'double-rare'               => 'doble_rara',
            'illustration-rare'         => 'ilustracion',
            'ultra-rare'                => 'ultra_rara',
            'special-illustration-rare' => 'ilustracion_especial',
            'hyper-rare'                => 'hiper_rara',
            'secret-rare'               => 'hiper_rara',
            'radiant-rare'              => 'excepciones',
            'promo'                     => 'excepciones',
            // Sin cartas en local, pero existen en TCGdex
            'ace-spec-rare'             => 'ace_spec',
            'rare-holo'                 => 'rara',
            'full-art-trainer'          => 'ultra_rara',
            'shiny-ultra-rare'          => 'ilustracion_especial',
            'black-white-rare'          => 'hiper_rara',
            'classic-collection'        => 'excepciones',
            'one-diamond'               => 'excepciones',
            'crown'                     => 'excepciones',
            'none'                      => 'excepciones',
        ];

        foreach ($esperado as $clave => $categoria) {
            $this->assertSame($categoria, Rarezas::categoria($clave), "rareza_key '{$clave}'");
        }

        // Sin hidratar no hay rareza: null se queda null, no se inventa
        $this->assertNull(Rarezas::categoria(null));
        $this->assertNull(Rarezas::categoria(''));

        // Toda clave que CatalogoTcg conoce cae en una de las diez
        foreach (array_keys(\App\Support\CatalogoTcg::RAREZAS) as $clave) {
            $this->assertContains(Rarezas::categoria($clave), Rarezas::ORDEN, "rareza_key '{$clave}'");
        }
    }

    public function test_cada_categoria_tiene_simbolo_orden_y_nombre_en_los_dos_idiomas(): void
    {
        $this->assertCount(10, Rarezas::ORDEN);
        $this->assertSame('comun', Rarezas::ORDEN[0]);
        $this->assertSame('excepciones', Rarezas::ORDEN[9]);

        foreach (Rarezas::ORDEN as $categoria) {
            $this->assertArrayHasKey($categoria, Rarezas::SIMBOLOS);
        }
        $this->assertNull(Rarezas::SIMBOLOS['excepciones']);
        $this->assertSame(['forma' => 'estrella', 'color' => 'dorado', 'cantidad' => 3], Rarezas::SIMBOLOS['hiper_rara']);

        app()->setLocale('es');
        $this->assertSame('Rara Ilustración Especial', Rarezas::nombre('ilustracion_especial'));
        $this->assertSame('Excepciones', Rarezas::nombre('excepciones'));

        app()->setLocale('en');
        $this->assertSame('Special Illustration Rare', Rarezas::nombre('ilustracion_especial'));
        $this->assertSame('Other', Rarezas::nombre('excepciones'));
    }

    // --- Una rareza que TCGdex se invente mañana no rompe nada ---
    // Se hidrata la carta con un valor que no existe en ningún catálogo: se
    // guarda (como slug, para poder remapearla), la carta responde con la
    // categoría "excepciones" y el filtro la encuentra.
    public function test_una_rareza_desconocida_de_tcgdex_cae_en_excepciones(): void
    {
        $carta = Carta::create(['tcgdex_id' => 'xx1-1', 'set_id' => 'xx1', 'nombre_es' => 'Misterio']);

        Http::fake([
            'api.tcgdex.net/v2/es/cards/xx1-1' => Http::response([
                'id'      => 'xx1-1',
                'localId' => '1',
                'name'    => 'Misterio',
                'rarity'  => 'Rareza Inventada 2027',
                'types'   => ['Psíquico'],
            ]),
            'api.tcgdex.net/v2/en/cards/xx1-1' => Http::response(null, 404),
        ]);

        $this->getJson("/api/cartas/{$carta->id}")
             ->assertStatus(200)
             ->assertJsonPath('rareza_categoria', 'excepciones')
             ->assertJsonPath('rareza', 'Excepciones')
             ->assertJsonPath('rareza_simbolo', null);

        $this->assertDatabaseHas('cartas', ['id' => $carta->id, 'rareza_key' => 'rareza-inventada-2027']);

        $this->getJson('/api/cartas?rareza=excepciones')
             ->assertJsonPath('total', 1)
             ->assertJsonPath('data.0.id', $carta->id);

        $this->withHeader('Accept-Language', 'en')
             ->getJson("/api/cartas/{$carta->id}")
             ->assertJsonPath('rareza', 'Other');
    }

    public function test_los_filtros_traen_solo_las_categorias_presentes_en_orden_canonico(): void
    {
        Http::fake();

        // Desordenadas a propósito y con dos claves finas de la misma
        // categoría (rare + holo-rare-v → rara). Sin ninguna "comun".
        foreach (['ultra-rare', 'holo-rare-v', 'promo', 'rare', 'uncommon', 'hyper-rare'] as $i => $clave) {
            Carta::create(['nombre' => "Carta {$i}", 'rareza_key' => $clave]);
        }
        Carta::create(['nombre' => 'Sin hidratar']); // rareza_key null: no cuenta

        $respuesta = $this->withHeader('Accept-Language', 'es')->getJson('/api/cartas/filtros');

        $respuesta->assertStatus(200);
        $rarezas = $respuesta->json('rarezas');

        $this->assertSame(
            ['infrecuente', 'rara', 'ultra_rara', 'hiper_rara', 'excepciones'],
            array_column($rarezas, 'clave')
        );
        $this->assertSame([
            'clave'    => 'ultra_rara',
            'etiqueta' => 'Ultra Rara',
            'simbolo'  => ['forma' => 'estrella', 'color' => 'plateado', 'cantidad' => 2],
        ], $rarezas[2]);
        $this->assertNull($rarezas[4]['simbolo']);

        Http::assertNothingSent();
    }

    public function test_el_catalogo_filtra_por_categoria(): void
    {
        $promo    = Carta::create(['nombre' => 'Promo',    'rareza_key' => 'promo']);
        $radiante = Carta::create(['nombre' => 'Radiante', 'rareza_key' => 'radiant-rare']);
        $rara     = Carta::create(['nombre' => 'Rara',     'rareza_key' => 'rare']);
        $v        = Carta::create(['nombre' => 'V',        'rareza_key' => 'holo-rare-v']);
        Carta::create(['nombre' => 'Común',  'rareza_key' => 'common']);
        Carta::create(['nombre' => 'Sin hidratar']);

        // Excepciones: las promos y demás casos raros, y nada más
        $ids = collect($this->getJson('/api/cartas?rareza=excepciones')->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$promo->id, $radiante->id], $ids);

        // "rara" agrupa varias claves finas
        $ids = collect($this->getJson('/api/cartas?rareza=rara')->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$rara->id, $v->id], $ids);

        // Los enlaces de antes del cambio siguen funcionando: una clave fina
        // o un texto de TCGdex se resuelven a su categoría
        $this->getJson('/api/cartas?rareza=holo-rare-v')->assertJsonPath('total', 2);
        $this->getJson('/api/cartas?rareza=Común')->assertJsonPath('total', 1);

        // Un valor sin sentido no devuelve nada: ni las cartas sin hidratar
        $this->getJson('/api/cartas?rareza=loquesea')->assertJsonPath('total', 0);
    }

    // --- Búsqueda en vivo: una categoría son varias rarezas de TCGdex ---
    // Se piden todas de una vez con OR y con igualdad exacta ("eq:"), y a
    // cada catálogo solo las que existen en su idioma: "Rare Holo" (clásica)
    // no existe en el español, así que a /v2/es no se le pide.
    public function test_la_busqueda_en_vivo_traduce_la_categoria_a_las_rarezas_de_tcgdex(): void
    {
        Http::fake([
            'api.tcgdex.net/v2/es/cards?*' => Http::response([]),
            'api.tcgdex.net/v2/en/cards?*' => Http::response([]),
        ]);

        $this->withHeader('Accept-Language', 'es')
             ->getJson('/api/cartas/buscar?rareza=rara')
             ->assertStatus(200);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v2/es/cards')
            && $r['rarity'] === 'eq:Rara|Holo Rara|Holo Rara V|Holo Rara VMAX|Holo Rara VSTAR');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/v2/en/cards')
            && $r['rarity'] === 'eq:Rare|Holo Rare|Holo Rare V|Holo Rare VMAX|Holo Rare VSTAR|Rare Holo|Rare Holo LV.X|Rare PRIME|LEGEND');
    }

    public function test_la_carta_expone_categoria_y_simbolo(): void
    {
        $carta = Carta::create(['nombre' => 'Charizard ex', 'rareza_key' => 'special-illustration-rare']);

        $this->withHeader('Accept-Language', 'es')
             ->getJson("/api/cartas/{$carta->id}")
             ->assertJsonPath('rareza', 'Rara Ilustración Especial')
             ->assertJsonPath('rareza_categoria', 'ilustracion_especial')
             ->assertJsonPath('rareza_simbolo', ['forma' => 'estrella', 'color' => 'dorado', 'cantidad' => 2]);

        $this->withHeader('Accept-Language', 'en')
             ->getJson("/api/cartas/{$carta->id}")
             ->assertJsonPath('rareza', 'Special Illustration Rare');

        // Sin hidratar: todo a null, nada inventado
        $vacia = Carta::create(['nombre' => 'Sin hidratar']);
        $this->getJson("/api/cartas/{$vacia->id}")
             ->assertJsonPath('rareza', null)
             ->assertJsonPath('rareza_categoria', null)
             ->assertJsonPath('rareza_simbolo', null);
    }
}
