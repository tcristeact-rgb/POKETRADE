<?php

namespace Tests\Feature;

use App\Models\Carta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// --- GET /cartas/aleatorias: el carrusel de la portada ---
// (spec 2026-09-19-portada-destacadas-aleatorias-y-registro-sin-nacionalidad)
class CartasAleatoriasTest extends TestCase
{
    use RefreshDatabase;

    private function sembrar(int $conImagen, int $sinImagen = 0): void
    {
        for ($i = 1; $i <= $conImagen; $i++) {
            Carta::create(['nombre' => "Con imagen {$i}", 'imagen_es' => "https://assets.tcgdex.net/es/sv/sv01/{$i}"]);
        }
        for ($i = 1; $i <= $sinImagen; $i++) {
            Carta::create(['nombre' => "Sin imagen {$i}"]);
        }
    }

    public function test_devuelve_doce_cartas_con_imagen_por_defecto_y_no_llama_a_tcgdex(): void
    {
        Http::fake();
        $this->sembrar(conImagen: 20, sinImagen: 5);

        $respuesta = $this->getJson('/api/cartas/aleatorias');

        $respuesta->assertStatus(200)->assertJsonCount(12);
        foreach ($respuesta->json() as $carta) {
            $this->assertNotNull($carta['imagen_low'], "La carta {$carta['id']} no tiene imagen");
        }

        Http::assertNothingSent();
    }

    public function test_la_cantidad_se_respeta_y_se_acota_a_24(): void
    {
        $this->sembrar(conImagen: 30);

        $this->getJson('/api/cartas/aleatorias?cantidad=5')->assertJsonCount(5);
        $this->getJson('/api/cartas/aleatorias?cantidad=100')->assertJsonCount(24);
        $this->getJson('/api/cartas/aleatorias?cantidad=0')->assertJsonCount(1);
    }

    public function test_el_orden_es_aleatorio(): void
    {
        $this->sembrar(conImagen: 24);

        // 24! ordenaciones posibles: que diez peticiones den siempre la misma
        // es, a efectos prácticos, imposible
        $ordenes = [];
        for ($i = 0; $i < 10; $i++) {
            $ordenes[] = implode(',', array_column($this->getJson('/api/cartas/aleatorias?cantidad=24')->json(), 'id'));
        }

        $this->assertGreaterThan(1, count(array_unique($ordenes)));
    }

    public function test_sin_cartas_devuelve_una_lista_vacia(): void
    {
        $this->getJson('/api/cartas/aleatorias')->assertStatus(200)->assertExactJson([]);
    }
}
