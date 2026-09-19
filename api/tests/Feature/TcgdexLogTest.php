<?php

namespace Tests\Feature;

use App\Services\TcgdexService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

// TcgdexService::get() deja rastro en el log cada vez que devuelve null: sin
// esto, un TCGdex caído era un 503 para el usuario y nada en el log de Render.
// El contrato de retorno (array / [] / null) no cambia; solo se hace visible.
class TcgdexLogTest extends TestCase
{
    private function tcgdex(): TcgdexService
    {
        return app(TcgdexService::class);
    }

    public function test_cuando_tcgdex_no_responde_se_registra_un_warning_con_ruta_idioma_y_causa(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $mensaje, array $contexto) =>
                $mensaje === 'TCGdex no responde'
                && $contexto['ruta'] === 'cards/sv03.5-006'
                && $contexto['idioma'] === 'es'
                && $contexto['excepcion'] === ConnectionException::class
                && str_contains($contexto['mensaje'], 'timed out'));

        $this->assertNull($this->tcgdex()->obtenerCarta('sv03.5-006', 'es'));
    }

    public function test_un_5xx_se_registra_con_el_status_y_sigue_devolviendo_null(): void
    {
        Http::fake(['api.tcgdex.net/*' => Http::response(null, 503)]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $mensaje, array $contexto) =>
                $mensaje === 'TCGdex respondió con error'
                && $contexto['ruta'] === 'sets/sv03.5'
                && $contexto['idioma'] === 'en'
                && $contexto['status'] === 503);

        $this->assertNull($this->tcgdex()->obtenerSet('sv03.5', 'en'));
    }

    public function test_un_200_que_no_es_json_devuelve_null_y_se_registra_en_vez_de_reventar(): void
    {
        // Un WAF/CDN intermedio contestando con su propia página. Antes, un cuerpo
        // que parseaba a escalar hacía saltar el TypeError del tipo de retorno.
        Http::fake(['api.tcgdex.net/*' => Http::response('42', 200, ['Content-Type' => 'application/json'])]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $mensaje, array $contexto) =>
                $mensaje === 'TCGdex respondió sin JSON' && $contexto['status'] === 200);

        $this->assertNull($this->tcgdex()->obtenerCarta('sv03.5-006', 'es'));
    }

    public function test_un_404_sigue_siendo_una_respuesta_vacia_y_no_se_registra(): void
    {
        // El 404 es parte del contrato (el catálogo español no tiene ese recurso),
        // se cachea y no es un fallo: nada que avisar.
        Http::fake(['api.tcgdex.net/*' => Http::response(null, 404)]);

        Log::shouldReceive('warning')->never();

        $this->assertSame([], $this->tcgdex()->obtenerCarta('base1-1', 'es'));
    }

    public function test_una_excepcion_que_no_es_de_conexion_ya_no_se_disfraza_de_tcgdex_caido(): void
    {
        // Cambio de comportamiento deliberado: un bug nuestro dentro del cliente
        // debe reventar (500), no convertirse en un "TCGdex no contestó" (503).
        Http::fake(fn () => throw new \RuntimeException('bug nuestro'));

        $this->expectException(\RuntimeException::class);

        $this->tcgdex()->obtenerCarta('sv03.5-006', 'es');
    }
}
