<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase; // Resetea la BD entre cada test
use Tests\TestCase;
use App\Models\User;
use App\Models\Carta;
use App\Models\Tradeo;
use App\Models\Inventario;

class TradeoTest extends TestCase
{
    // RefreshDatabase garantiza que cada test empieza con la BD limpia
    use RefreshDatabase;

    // --- Método auxiliar: crear usuario ---
    // Evita repetir el mismo código en cada test
    // Por defecto crea un usuario con rol 'cliente'
    private function crearUsuario($rol = 'cliente')
    {
        return User::create([
            'nombre'   => 'Test',
            'apellido' => 'Usuario',
            'email'    => "test_{$rol}@test.com", // Email único según el rol
            'password' => bcrypt('123456'),
            'rol'      => $rol,
        ]);
    }

    // --- Método auxiliar: crear carta ---
    // Evita repetir el mismo código en cada test
    // Por defecto crea una carta llamada 'Charizard'
    private function crearCarta($nombre = 'Charizard')
    {
        return Carta::create([
            'nombre' => $nombre,
            'tipo'   => 'Fuego',
            'rareza' => 'Rara Holo',
        ]);
    }

    // --- Test 1: Ver tradeos público ---
    // Comprueba que cualquier usuario sin login puede ver la lista de tradeos
    public function test_cualquiera_puede_ver_tradeos()
    {
        // GET sin token de autenticación
        $respuesta = $this->getJson('/api/tradeos');

        // Debe devolver 200 aunque no haya tradeos
        $respuesta->assertStatus(200);
    }

    // --- Test 2: No autenticado no puede crear tradeo ---
    // Comprueba que sin token JWT no se puede crear un tradeo
    public function test_usuario_no_autenticado_no_puede_crear_tradeo()
    {
        // Intentamos crear un tradeo sin enviar token
        $respuesta = $this->postJson('/api/tradeos', [
            'descripcion'   => 'Test tradeo',
            'cartas_ofrece' => [1],
            'cartas_busca'  => [2],
        ]);

        // Debe devolver 401 (no autenticado)
        $respuesta->assertStatus(401);
    }

    // --- Test 3: Usuario autenticado puede crear tradeo ---
    // Comprueba el flujo completo de creación de un tradeo
    public function test_usuario_autenticado_puede_crear_tradeo()
    {
        // Creamos usuario y dos cartas usando los métodos auxiliares
        $usuario = $this->crearUsuario();
        $carta1  = $this->crearCarta('Pikachu');
        $carta2  = $this->crearCarta('Mewtwo');

        // El controlador exige que el usuario tenga en su inventario las cartas
        // que va a ofrecer (las retira al publicar el tradeo), así que se la añadimos
        Inventario::create([
            'user_id'  => $usuario->id,
            'carta_id' => $carta1->id,
            'cantidad' => 1,
        ]);

        // Generamos el token JWT del usuario
        $token = auth()->login($usuario);

        // Creamos el tradeo con el token en el header
        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
                          ->postJson('/api/tradeos', [
                              'descripcion'   => 'Busco Mewtwo, ofrezco Pikachu',
                              'cartas_ofrece' => [$carta1->id], // IDs de cartas que ofrece
                              'cartas_busca'  => [$carta2->id], // IDs de cartas que busca
                          ]);

        // Debe devolver 201 y el mensaje de éxito
        $respuesta->assertStatus(201)
                  ->assertJsonFragment(['mensaje' => 'Tradeo publicado correctamente']);

        // Verificamos que el tradeo se ha guardado en la BD
        $this->assertDatabaseHas('tradeos', [
            'user_id'     => $usuario->id,
            'descripcion' => 'Busco Mewtwo, ofrezco Pikachu',
        ]);
    }

    // --- Test 4: Tradeo sin cartas falla ---
    // Comprueba que no se puede crear un tradeo con arrays vacíos
    public function test_tradeo_sin_cartas_falla()
    {
        $usuario = $this->crearUsuario();
        $token   = auth()->login($usuario);

        // Enviamos el tradeo con arrays de cartas vacíos
        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
                          ->postJson('/api/tradeos', [
                              'descripcion'   => 'Tradeo sin cartas',
                              'cartas_ofrece' => [], // Array vacío — debe fallar
                              'cartas_busca'  => [], // Array vacío — debe fallar
                          ]);

        // Debe devolver 422 porque la validación exige al menos 1 carta
        $respuesta->assertStatus(422);
    }

    // --- Test 5: Solo el propietario puede eliminar su tradeo ---
    // Comprueba que un usuario no puede eliminar el tradeo de otro usuario
    public function test_solo_propietario_puede_eliminar_tradeo()
    {
        // Creamos dos usuarios diferentes
        $usuario1 = $this->crearUsuario('cliente');
        $usuario2 = User::create([
            'nombre'   => 'Otro',
            'apellido' => 'Usuario',
            'email'    => 'otro@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'cliente',
        ]);

        // Creamos dos cartas para el tradeo
        $carta1 = $this->crearCarta('Pikachu');
        $carta2 = $this->crearCarta('Mewtwo');

        // Creamos un tradeo que pertenece al usuario1
        $tradeo = Tradeo::create([
            'user_id'     => $usuario1->id,
            'descripcion' => 'Tradeo de usuario1',
            'estado'      => 'activo',
        ]);

        // Asociamos las cartas al tradeo mediante las tablas pivote
        $tradeo->cartasOfrece()->attach([$carta1->id]);
        $tradeo->cartasBusca()->attach([$carta2->id]);

        // Generamos el token del usuario2 (que NO es el propietario)
        $token2 = auth()->login($usuario2);

        // El usuario2 intenta eliminar el tradeo del usuario1
        $respuesta = $this->withHeader('Authorization', "Bearer {$token2}")
                          ->deleteJson("/api/tradeos/{$tradeo->id}");

        // Debe devolver 403 (prohibido) porque no es el propietario
        $respuesta->assertStatus(403);
    }

    // --- Test 6: Propietario puede eliminar su propio tradeo ---
    // Comprueba que el usuario que creó el tradeo sí puede eliminarlo
    public function test_propietario_puede_eliminar_su_tradeo()
    {
        // Creamos usuario y cartas
        $usuario = $this->crearUsuario();
        $carta1  = $this->crearCarta('Pikachu');
        $carta2  = $this->crearCarta('Mewtwo');

        // Creamos el tradeo perteneciente al usuario
        $tradeo = Tradeo::create([
            'user_id'     => $usuario->id,
            'descripcion' => 'Mi tradeo',
            'estado'      => 'activo',
        ]);

        // Asociamos las cartas al tradeo
        $tradeo->cartasOfrece()->attach([$carta1->id]);
        $tradeo->cartasBusca()->attach([$carta2->id]);

        // Generamos el token del propietario
        $token = auth()->login($usuario);

        // El propietario elimina su propio tradeo
        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
                          ->deleteJson("/api/tradeos/{$tradeo->id}");

        // Debe devolver 200 y el mensaje de éxito
        $respuesta->assertStatus(200)
                  ->assertJsonFragment(['mensaje' => 'Tradeo eliminado correctamente']);

        // Verificamos que el tradeo ya no existe en la BD
        $this->assertDatabaseMissing('tradeos', ['id' => $tradeo->id]);
    }
}