<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Carta;
use App\Models\Tradeo;

class TradeoTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario($rol = 'cliente')
    {
        return User::create([
            'nombre'   => 'Test',
            'apellido' => 'Usuario',
            'email'    => "test_{$rol}@test.com",
            'password' => bcrypt('123456'),
            'rol'      => $rol,
        ]);
    }

    private function crearCarta($nombre = 'Charizard')
    {
        return Carta::create([
            'nombre' => $nombre,
            'tipo'   => 'Fuego',
            'rareza' => 'Rara Holo',
        ]);
    }

    // Prueba: cualquiera puede ver los tradeos
    public function test_cualquiera_puede_ver_tradeos()
    {
        $respuesta = $this->getJson('/api/tradeos');
        $respuesta->assertStatus(200);
    }

    // Prueba: usuario no autenticado no puede crear tradeo
    public function test_usuario_no_autenticado_no_puede_crear_tradeo()
    {
        $respuesta = $this->postJson('/api/tradeos', [
            'descripcion'   => 'Test tradeo',
            'cartas_ofrece' => [1],
            'cartas_busca'  => [2],
        ]);

        $respuesta->assertStatus(401);
    }

    // Prueba: usuario autenticado puede crear tradeo
    public function test_usuario_autenticado_puede_crear_tradeo()
    {
        $usuario = $this->crearUsuario();
        $carta1  = $this->crearCarta('Pikachu');
        $carta2  = $this->crearCarta('Mewtwo');
        $token   = auth()->login($usuario);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
                          ->postJson('/api/tradeos', [
                              'descripcion'   => 'Busco Mewtwo, ofrezco Pikachu',
                              'cartas_ofrece' => [$carta1->id],
                              'cartas_busca'  => [$carta2->id],
                          ]);

        $respuesta->assertStatus(201)
                  ->assertJsonFragment(['mensaje' => 'Tradeo publicado correctamente']);

        $this->assertDatabaseHas('tradeos', [
            'user_id'     => $usuario->id,
            'descripcion' => 'Busco Mewtwo, ofrezco Pikachu',
        ]);
    }

    // Prueba: tradeo sin cartas falla
    public function test_tradeo_sin_cartas_falla()
    {
        $usuario = $this->crearUsuario();
        $token   = auth()->login($usuario);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
                          ->postJson('/api/tradeos', [
                              'descripcion'   => 'Tradeo sin cartas',
                              'cartas_ofrece' => [],
                              'cartas_busca'  => [],
                          ]);

        $respuesta->assertStatus(400);
    }

    // Prueba: solo el propietario puede eliminar su tradeo
    public function test_solo_propietario_puede_eliminar_tradeo()
    {
        $usuario1 = $this->crearUsuario('cliente');
        $usuario2 = User::create([
            'nombre'   => 'Otro',
            'apellido' => 'Usuario',
            'email'    => 'otro@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'cliente',
        ]);

        $carta1 = $this->crearCarta('Pikachu');
        $carta2 = $this->crearCarta('Mewtwo');

        $tradeo = Tradeo::create([
            'user_id'     => $usuario1->id,
            'descripcion' => 'Tradeo de usuario1',
            'estado'      => 'activo',
        ]);

        $tradeo->cartasOfrece()->attach([$carta1->id]);
        $tradeo->cartasBusca()->attach([$carta2->id]);

        $token2 = auth()->login($usuario2);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token2}")
                          ->deleteJson("/api/tradeos/{$tradeo->id}");

        $respuesta->assertStatus(403);
    }

    // Prueba: propietario puede eliminar su tradeo
    public function test_propietario_puede_eliminar_su_tradeo()
    {
        $usuario = $this->crearUsuario();
        $carta1  = $this->crearCarta('Pikachu');
        $carta2  = $this->crearCarta('Mewtwo');

        $tradeo = Tradeo::create([
            'user_id'     => $usuario->id,
            'descripcion' => 'Mi tradeo',
            'estado'      => 'activo',
        ]);

        $tradeo->cartasOfrece()->attach([$carta1->id]);
        $tradeo->cartasBusca()->attach([$carta2->id]);

        $token = auth()->login($usuario);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
                          ->deleteJson("/api/tradeos/{$tradeo->id}");

        $respuesta->assertStatus(200)
                  ->assertJsonFragment(['mensaje' => 'Tradeo eliminado correctamente']);

        $this->assertDatabaseMissing('tradeos', ['id' => $tradeo->id]);
    }
}