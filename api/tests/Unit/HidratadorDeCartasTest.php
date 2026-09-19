<?php

namespace Tests\Unit;

use App\Services\HidratadorDeCartas;
use Tests\TestCase;

// El mapeo TCGdex → columnas vive en un solo sitio. Este test fija los nombres
// de columna de la fila del upsert del cache-aside: si cambian, cambia lo que
// SetController mete en la BD, y eso tiene que ser una decisión, no un efecto.
class HidratadorDeCartasTest extends TestCase
{
    public function test_fila_de_resumen_tiene_exactamente_las_columnas_del_upsert(): void
    {
        $fila = app(HidratadorDeCartas::class)->filaDeResumen([
            'id'      => 'sv08-005',
            'localId' => '005',
            'name'    => 'Carta 5',
            'image'   => 'https://assets.tcgdex.net/es/sv/sv08/005',
        ], 'es', 'sv08');

        $this->assertSame(
            ['tcgdex_id', 'nombre_es', 'imagen_es', 'numero', 'set_id', 'created_at', 'updated_at'],
            array_keys($fila)
        );
        $this->assertSame('sv08-005', $fila['tcgdex_id']);
        $this->assertSame('Carta 5', $fila['nombre_es']);
        $this->assertSame('https://assets.tcgdex.net/es/sv/sv08/005', $fila['imagen_es']);
        $this->assertSame('005', $fila['numero']);
        $this->assertSame('sv08', $fila['set_id']);
        $this->assertArrayNotHasKey('descripcion_es', $fila);
    }

    public function test_fila_de_resumen_rellena_con_null_lo_que_el_resumen_no_trae(): void
    {
        // Todas las filas de un upsert llevan las mismas claves: una carta sin
        // imagen ni número sigue llevando las dos columnas, a null
        $fila = app(HidratadorDeCartas::class)->filaDeResumen(['id' => 'sv08-006', 'name' => 'Sin imagen'], 'en', 'sv08');

        $this->assertNull($fila['imagen_en']);
        $this->assertNull($fila['numero']);
        $this->assertArrayHasKey('nombre_en', $fila);
        $this->assertArrayNotHasKey('nombre_es', $fila);
    }
}
