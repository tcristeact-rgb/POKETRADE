<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase; // Resetea la BD entre cada test
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use App\Models\Carta;

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
    // recientes igual que las cacheadas desde el resumen de un set)
    private function crearRelleno(int $n): void
    {
        Carta::insert(array_map(fn ($i) => [
            'nombre'     => "Relleno {$i}",
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
        $this->crearRelleno(10);
        Carta::create(['nombre' => 'Única con precio', 'precio_cardmarket' => 12]);

        $res = $this->getJson('/api/cartas/destacadas');

        $res->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertSame('Única con precio', $res->json('data.0.nombre'));
    }

    public function test_incluye_los_campos_que_necesita_el_hero(): void
    {
        Carta::create([
            'nombre'            => 'Charizard ex',
            'set_expansion'     => '151',
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
        Carta::create(['nombre' => 'Primera', 'precio_cardmarket' => 10]);

        $this->withHeader('Accept-Language', 'es')->getJson('/api/cartas/destacadas');

        // El inglés todavía no ha pasado por aquí: su hueco está vacío
        $this->assertTrue(Cache::has('cartas.destacadas.es'));
        $this->assertFalse(Cache::has('cartas.destacadas.en'));

        $this->withHeader('Accept-Language', 'en')->getJson('/api/cartas/destacadas');

        $this->assertTrue(Cache::has('cartas.destacadas.en'));
    }
}
