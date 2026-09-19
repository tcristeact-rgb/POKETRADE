<?php

namespace Tests\Feature;

use App\Models\Carta;
use App\Models\Inventario;
use App\Models\Serie;
use App\Models\Set;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// GET /api/inventario: lo que el modal de detalle del inventario necesita
// (spec 2026-09-19-detalle-carta-en-inventario). El nombre del set llega
// porque Carta declara $with = ['set']; el test de consultas protege eso.
class InventarioTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioConCartas(int $n): User
    {
        $usuario = User::create([
            'nombre' => 'Inv', 'apellido' => 'Test', 'email' => 'inv@poketrade.es',
            'password' => Hash::make('secreta123'), 'rol' => 'cliente', 'email_verified_at' => now(),
        ]);
        $serie = Serie::create(['tcgdex_id' => 'sv', 'nombre' => 'Escarlata y Púrpura']);

        // Cada carta en un set distinto: si Carta perdiera su $with = ['set'],
        // el accessor set_expansion haría una consulta por carta
        for ($i = 1; $i <= $n; $i++) {
            Set::create(['tcgdex_id' => "sv0{$i}", 'serie_id' => $serie->id, 'nombre' => "Set {$i}"]);
            $carta = Carta::create(['nombre' => "Carta {$i}", 'tcgdex_id' => "sv0{$i}-001", 'set_id' => "sv0{$i}"]);
            Inventario::create(['user_id' => $usuario->id, 'carta_id' => $carta->id, 'cantidad' => $i]);
        }

        return $usuario;
    }

    public function test_el_listado_trae_el_nombre_del_set_de_cada_carta(): void
    {
        $usuario = $this->usuarioConCartas(2);

        $res = $this->actingAs($usuario, 'api')->getJson('/api/inventario');

        $res->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.carta.set_expansion', 'Set 1')
            ->assertJsonPath('1.carta.set_expansion', 'Set 2')
            ->assertJsonPath('1.cantidad', 2);
    }

    public function test_el_numero_de_consultas_no_crece_con_el_numero_de_cartas(): void
    {
        $usuario = $this->usuarioConCartas(5);

        DB::enableQueryLog();
        $this->actingAs($usuario, 'api')->getJson('/api/inventario')->assertOk()->assertJsonCount(5);
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        // inventario + cartas + sets (el usuario ya está en memoria con actingAs).
        // Sin el $with del modelo serían 5 más, una por carta.
        $this->assertLessThanOrEqual(4, $consultas, "hizo {$consultas} consultas");
    }
}
