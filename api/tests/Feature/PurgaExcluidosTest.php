<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase; // Resetea la BD entre cada test
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\Carta;
use App\Models\Inventario;
use App\Models\Serie;
use App\Models\Set;
use App\Models\Tradeo;
use App\Models\User;

class PurgaExcluidosTest extends TestCase
{
    // RefreshDatabase garantiza que cada test empieza con la BD limpia
    use RefreshDatabase;

    // Escenario: la serie excluida (Pocket) importada en BD con un set,
    // una carta cacheada, una entrada de inventario y un tradeo que la
    // referencia; y una serie sana (sv) que debe sobrevivir intacta
    private function crearEscenario(): array
    {
        config(['tcgdex.series_excluidas' => ['tcgp']]);

        // setsExcluidos() consulta TCGdex: se simula el detalle de tcgp
        Http::fake([
            'api.tcgdex.net/v2/en/series/tcgp' => Http::response([
                'id' => 'tcgp', 'name' => 'Pocket',
                'sets' => [['id' => 'A1', 'name' => 'Genetic Apex']],
            ]),
            'api.tcgdex.net/*' => Http::response(null, 404),
        ]);

        $usuario = User::create([
            'nombre' => 'Ash', 'apellido' => 'Ketchum', 'email' => 'ash@test.com',
            'password' => bcrypt('123456'), 'rol' => 'cliente',
        ]);

        $pocket = Serie::create(['tcgdex_id' => 'tcgp', 'nombre' => 'Pocket']);
        Set::create(['tcgdex_id' => 'A1', 'serie_id' => $pocket->id, 'nombre' => 'Genetic Apex', 'synced_at' => now()]);
        $cartaPocket = Carta::create(['nombre' => 'Mewtwo ex', 'tcgdex_id' => 'A1-286', 'set_id' => 'A1']);

        $sana = Serie::create(['tcgdex_id' => 'sv', 'nombre' => 'Escarlata y Púrpura']);
        Set::create(['tcgdex_id' => 'sv03.5', 'serie_id' => $sana->id, 'nombre' => '151']);
        $cartaSana = Carta::create(['nombre' => 'Charizard ex', 'tcgdex_id' => 'sv03.5-006', 'set_id' => 'sv03.5']);

        Inventario::create(['user_id' => $usuario->id, 'carta_id' => $cartaPocket->id, 'cantidad' => 1]);

        $tradeo = Tradeo::create(['user_id' => $usuario->id, 'estado' => 'activo']);
        $tradeo->cartasOfrece()->attach($cartaSana->id);
        $tradeo->cartasBusca()->attach($cartaPocket->id);

        $tradeoLimpio = Tradeo::create(['user_id' => $usuario->id, 'estado' => 'activo']);
        $tradeoLimpio->cartasOfrece()->attach($cartaSana->id);
        $tradeoLimpio->cartasBusca()->attach($cartaSana->id);

        return compact('pocket', 'cartaPocket', 'sana', 'cartaSana', 'tradeo', 'tradeoLimpio');
    }

    // --- Test 1: El dry-run informa pero NO borra nada ---
    public function test_dry_run_informa_sin_borrar()
    {
        $this->crearEscenario();

        $this->artisan('tcgdex:purgar-excluidos')
             ->expectsOutputToContain('Series en BD a eliminar: 1')
             ->expectsOutputToContain('Cartas cacheadas a eliminar: 1')
             ->expectsOutputToContain('Dry-run: NO se ha borrado nada.')
             ->assertExitCode(0);

        $this->assertDatabaseCount('series', 2);
        $this->assertDatabaseCount('cartas', 2);
        $this->assertDatabaseCount('tradeos', 2);
    }

    // --- Test 2: --force borra la serie excluida y sus referencias ---
    public function test_force_purga_serie_excluida_con_referencias()
    {
        $e = $this->crearEscenario();

        $this->artisan('tcgdex:purgar-excluidos --force')
             ->expectsOutputToContain('Purga completada: 1 series, 1 cartas, 1 entradas de inventario y 1 tradeos eliminados.')
             ->assertExitCode(0);

        // Todo lo de Pocket ha desaparecido: serie, set (cascade),
        // carta, inventario (cascade) y el tradeo que la buscaba
        $this->assertDatabaseMissing('series', ['tcgdex_id' => 'tcgp']);
        $this->assertDatabaseMissing('sets', ['tcgdex_id' => 'A1']);
        $this->assertDatabaseMissing('cartas', ['tcgdex_id' => 'A1-286']);
        $this->assertDatabaseMissing('inventario', ['carta_id' => $e['cartaPocket']->id]);
        $this->assertDatabaseMissing('tradeos', ['id' => $e['tradeo']->id]);

        // La serie sana y su mundo siguen intactos
        $this->assertDatabaseHas('series', ['tcgdex_id' => 'sv']);
        $this->assertDatabaseHas('sets', ['tcgdex_id' => 'sv03.5']);
        $this->assertDatabaseHas('cartas', ['tcgdex_id' => 'sv03.5-006']);
        $this->assertDatabaseHas('tradeos', ['id' => $e['tradeoLimpio']->id]);
    }

    // --- Test 3: Idempotente — sin nada importado no hace nada ---
    public function test_sin_nada_importado_no_hace_nada()
    {
        config(['tcgdex.series_excluidas' => ['tcgp']]);
        Http::fake(['api.tcgdex.net/*' => Http::response(null, 404)]);

        $this->artisan('tcgdex:purgar-excluidos --force')
             ->expectsOutputToContain('La BD no contiene nada de las series excluidas.')
             ->assertExitCode(0);
    }
}
