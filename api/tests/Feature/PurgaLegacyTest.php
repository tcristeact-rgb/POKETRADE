<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase; // Resetea la BD entre cada test
use Tests\TestCase;
use App\Models\Carta;
use App\Models\Inventario;
use App\Models\Tradeo;
use App\Models\User;

class PurgaLegacyTest extends TestCase
{
    // RefreshDatabase garantiza que cada test empieza con la BD limpia
    use RefreshDatabase;

    // Escenario: una carta legacy (sin tcgdex_id, como las del seed
    // antiguo de PokéAPI) enredada en un inventario y dos tradeos, y
    // una carta TCGdex sana con su propio tradeo limpio
    private function crearEscenario(): array
    {
        $usuario = User::create([
            'nombre'   => 'Ash',
            'apellido' => 'Ketchum',
            'email'    => 'ash@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'cliente',
        ]);

        $legacy = Carta::create(['nombre' => 'Dugtrio', 'imagen_url' => 'https://pokeapi.co/sprites/51.png']);
        $tcgdex = Carta::create(['nombre' => 'Charizard ex', 'tcgdex_id' => 'sv03.5-006', 'set_id' => 'sv03.5']);

        Inventario::create(['user_id' => $usuario->id, 'carta_id' => $legacy->id, 'cantidad' => 2]);
        Inventario::create(['user_id' => $usuario->id, 'carta_id' => $tcgdex->id, 'cantidad' => 1]);

        // Tradeo que OFRECE la legacy
        $tradeoOfrece = Tradeo::create(['user_id' => $usuario->id, 'estado' => 'activo']);
        $tradeoOfrece->cartasOfrece()->attach($legacy->id);
        $tradeoOfrece->cartasBusca()->attach($tcgdex->id);

        // Tradeo que BUSCA la legacy
        $tradeoBusca = Tradeo::create(['user_id' => $usuario->id, 'estado' => 'activo']);
        $tradeoBusca->cartasOfrece()->attach($tcgdex->id);
        $tradeoBusca->cartasBusca()->attach($legacy->id);

        // Tradeo limpio, solo con la carta TCGdex
        $tradeoLimpio = Tradeo::create(['user_id' => $usuario->id, 'estado' => 'activo']);
        $tradeoLimpio->cartasOfrece()->attach($tcgdex->id);
        $tradeoLimpio->cartasBusca()->attach($tcgdex->id);

        return compact('usuario', 'legacy', 'tcgdex', 'tradeoOfrece', 'tradeoBusca', 'tradeoLimpio');
    }

    // --- Test 1: El dry-run informa pero NO borra nada ---
    public function test_dry_run_informa_sin_borrar()
    {
        $e = $this->crearEscenario();

        $this->artisan('cartas:purgar-legacy')
             ->expectsOutputToContain('Cartas legacy encontradas: 1')
             ->expectsOutputToContain('Dry-run: NO se ha borrado nada.')
             ->assertExitCode(0);

        // Todo sigue en su sitio
        $this->assertDatabaseHas('cartas', ['id' => $e['legacy']->id]);
        $this->assertDatabaseCount('tradeos', 3);
        $this->assertDatabaseCount('inventario', 2);
    }

    // --- Test 2: --force borra la legacy y todo lo que la referencia ---
    public function test_force_purga_legacy_y_referencias()
    {
        $e = $this->crearEscenario();

        $this->artisan('cartas:purgar-legacy --force')
             ->expectsOutputToContain('Purga completada: 1 cartas, 1 entradas de inventario y 2 tradeos eliminados.')
             ->assertExitCode(0);

        // La carta legacy, su inventario y los 2 tradeos que la
        // referenciaban (en ofrece o en busca) han desaparecido
        $this->assertDatabaseMissing('cartas', ['id' => $e['legacy']->id]);
        $this->assertDatabaseMissing('inventario', ['carta_id' => $e['legacy']->id]);
        $this->assertDatabaseMissing('tradeos', ['id' => $e['tradeoOfrece']->id]);
        $this->assertDatabaseMissing('tradeos', ['id' => $e['tradeoBusca']->id]);

        // La carta TCGdex, su inventario y el tradeo limpio sobreviven
        $this->assertDatabaseHas('cartas', ['id' => $e['tcgdex']->id]);
        $this->assertDatabaseHas('inventario', ['carta_id' => $e['tcgdex']->id]);
        $this->assertDatabaseHas('tradeos', ['id' => $e['tradeoLimpio']->id]);

        // Sin pivotes huérfanos
        $this->assertDatabaseMissing('tradeo_cartas_ofrece', ['carta_id' => $e['legacy']->id]);
        $this->assertDatabaseMissing('tradeo_cartas_busca', ['carta_id' => $e['legacy']->id]);
    }

    // --- Test 3: Idempotente — sin legacy no hace nada ---
    public function test_sin_cartas_legacy_no_hace_nada()
    {
        Carta::create(['nombre' => 'Pikachu', 'tcgdex_id' => 'sv03.5-025']);

        $this->artisan('cartas:purgar-legacy --force')
             ->expectsOutputToContain('No hay cartas legacy en la BD')
             ->assertExitCode(0);

        $this->assertDatabaseCount('cartas', 1);
    }

    // --- Test 4: Las novedades de la home quedan limpias tras la purga ---
    // GET /api/cartas?orden=recientes (lo que consume la home) solo debe
    // devolver cartas TCG reales
    public function test_novedades_sin_legacy_tras_la_purga()
    {
        $this->crearEscenario();

        $this->artisan('cartas:purgar-legacy --force')->assertExitCode(0);

        $respuesta = $this->getJson('/api/cartas?orden=recientes&por_pagina=8');

        $respuesta->assertStatus(200)
                  ->assertJsonCount(1, 'data')
                  ->assertJsonFragment(['nombre' => 'Charizard ex'])
                  ->assertJsonMissing(['nombre' => 'Dugtrio']);
    }
}
