<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Carta;

class CartaTest extends TestCase
{
    use RefreshDatabase;

    // Prueba: cualquiera puede ver el catalogo
    public function test_cualquiera_puede_ver_catalogo()
    {
        Carta::create([
            'nombre' => 'Charizard',
            'tipo'   => 'Fuego',
            'rareza' => 'Rara Holo',
        ]);

        $respuesta = $this->getJson('/api/cartas');

        $respuesta->assertStatus(200)
                  ->assertJsonCount(1);
    }

    // Prueba: se puede ver el detalle de una carta
    public function test_se_puede_ver_detalle_de_carta()
    {
        $carta = Carta::create([
            'nombre' => 'Pikachu',
            'tipo'   => 'Electrico',
            'rareza' => 'Comun',
        ]);

        $respuesta = $this->getJson("/api/cartas/{$carta->id}");

        $respuesta->assertStatus(200)
                  ->assertJsonFragment(['nombre' => 'Pikachu']);
    }

    // Prueba: carta no encontrada devuelve 404
    public function test_carta_no_encontrada_devuelve_404()
    {
        $respuesta = $this->getJson('/api/cartas/9999');

        $respuesta->assertStatus(404);
    }

    // Prueba: cliente no puede crear cartas
    public function test_cliente_no_puede_crear_cartas()
    {
        $cliente = User::create([
            'nombre'   => 'Cliente',
            'apellido' => 'Test',
            'email'    => 'cliente@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'cliente',
        ]);

        $token = auth()->login($cliente);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
                          ->postJson('/api/cartas', [
                              'nombre' => 'Mewtwo',
                              'tipo'   => 'Psiquico',
                          ]);

        $respuesta->assertStatus(403);
    }

    // Prueba: admin puede crear cartas
    public function test_admin_puede_crear_cartas()
    {
        $admin = User::create([
            'nombre'   => 'Admin',
            'apellido' => 'Test',
            'email'    => 'admin@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'admin',
        ]);

        $token = auth()->login($admin);

        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
                          ->postJson('/api/cartas', [
                              'nombre' => 'Mewtwo',
                              'tipo'   => 'Psiquico',
                              'rareza' => 'Ultra Rara',
                          ]);

        $respuesta->assertStatus(201)
                  ->assertJsonFragment(['nombre' => 'Mewtwo']);
    }

    // Prueba: se puede filtrar por tipo
    public function test_se_puede_filtrar_cartas_por_tipo()
    {
        Carta::create(['nombre' => 'Charizard', 'tipo' => 'Fuego', 'rareza' => 'Rara']);
        Carta::create(['nombre' => 'Gyarados',  'tipo' => 'Agua',  'rareza' => 'Rara']);

        $respuesta = $this->getJson('/api/cartas?tipo=Fuego');

        $respuesta->assertStatus(200)
                  ->assertJsonCount(1)
                  ->assertJsonFragment(['nombre' => 'Charizard']);
    }
}