<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase; // Resetea la BD entre cada test
use Tests\TestCase;
use App\Models\User;
use App\Models\Carta;

class CartaTest extends TestCase
{
    // RefreshDatabase garantiza que cada test empieza con la BD limpia
    use RefreshDatabase;

    // --- Test 1: Catálogo público ---
    // Comprueba que cualquier usuario (sin login) puede ver el catálogo de cartas
    public function test_cualquiera_puede_ver_catalogo()
    {
        // Creamos una carta directamente en la BD
        Carta::create([
            'nombre' => 'Charizard',
            'tipo_key'   => 'fire',
            'rareza_key' => 'holo-rare',
        ]);

        // Hacemos GET al catálogo sin token de autenticación
        $respuesta = $this->getJson('/api/cartas');

        // Debe devolver 200 y exactamente 1 carta en el array "data"
        // (el catálogo responde con el paginador de Laravel)
        $respuesta->assertStatus(200)
                  ->assertJsonCount(1, 'data');
    }

    // --- Test 2: Detalle de carta ---
    // Comprueba que se puede ver el detalle de una carta específica por su ID
    public function test_se_puede_ver_detalle_de_carta()
    {
        // Creamos la carta y guardamos el objeto para obtener su ID
        $carta = Carta::create([
            'nombre' => 'Pikachu',
            'tipo_key'   => 'lightning',
            'rareza_key' => 'common',
        ]);

        // Hacemos GET al endpoint de detalle usando el ID de la carta creada
        $respuesta = $this->getJson("/api/cartas/{$carta->id}");

        // Debe devolver 200 y contener el nombre de la carta en el JSON
        $respuesta->assertStatus(200)
                  ->assertJsonFragment(['nombre' => 'Pikachu']);
    }

    // --- Test 3: Carta no encontrada ---
    // Comprueba que buscar una carta con ID inexistente devuelve 404
    public function test_carta_no_encontrada_devuelve_404()
    {
        // Buscamos una carta con un ID que no existe en la BD
        $respuesta = $this->getJson('/api/cartas/9999');

        // Debe devolver 404 (no encontrado)
        $respuesta->assertStatus(404);
    }

    // --- Test 4: Cliente no puede crear cartas ---
    // Comprueba que un usuario con rol 'cliente' no puede crear cartas
    // Solo los administradores tienen ese permiso (middleware EsAdmin)
    public function test_cliente_no_puede_crear_cartas()
    {
        // Creamos un usuario con rol 'cliente'
        $cliente = User::create([
            'nombre'   => 'Cliente',
            'apellido' => 'Test',
            'email'    => 'cliente@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'cliente',
        ]);

        // Generamos su token JWT para autenticarnos como él
        $token = auth()->login($cliente);

        // Intentamos crear una carta con el token del cliente
        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
                          ->postJson('/api/cartas', [
                              'nombre' => 'Mewtwo',
                              'tipo'   => 'Psiquico',
                          ]);

        // Debe devolver 403 (prohibido) porque el middleware EsAdmin lo bloquea
        $respuesta->assertStatus(403);
    }

    // --- Test 5: Admin puede crear cartas ---
    // Comprueba que un usuario con rol 'admin' sí puede crear cartas
    public function test_admin_puede_crear_cartas()
    {
        // Creamos un usuario con rol 'admin'
        $admin = User::create([
            'nombre'   => 'Admin',
            'apellido' => 'Test',
            'email'    => 'admin@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'admin',
        ]);

        // Generamos su token JWT para autenticarnos como administrador
        $token = auth()->login($admin);

        // Creamos una carta con el token del admin. El tipo va como clave
        // canónica; la rareza, como nombre en español, para comprobar que
        // el endpoint acepta las dos formas
        $respuesta = $this->withHeader('Authorization', "Bearer {$token}")
                          ->postJson('/api/cartas', [
                              'nombre' => 'Mewtwo',
                              'tipo'   => 'psychic',
                              'rareza' => 'Ultra Rara',
                          ]);

        // Debe devolver 201 (creado) y contener el nombre de la carta en el JSON
        $respuesta->assertStatus(201)
                  ->assertJsonFragment(['nombre' => 'Mewtwo']);

        // Y lo que se guarda es la clave, no el texto. El nombre va a la columna
        // del idioma en el que el admin lo escribió: una carta dada de alta a
        // mano tiene un solo nombre, no dos.
        $this->assertDatabaseHas('cartas', [
            'nombre_es'  => 'Mewtwo',
            'tipo_key'   => 'psychic',
            'rareza_key' => 'ultra-rare',
        ]);
    }

    // --- Test 5a: Un tipo que no existe en el TCG se rechaza ---
    // Antes se guardaba tal cual y quedaba una carta con un tipo inventado,
    // sin traducción posible, que además ensuciaba el filtro del catálogo
    public function test_no_se_puede_crear_una_carta_con_un_tipo_inventado()
    {
        $admin = User::create([
            'nombre'   => 'Admin',
            'apellido' => 'Test',
            'email'    => 'admin2@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'admin',
        ]);

        // "Eléctrico" es un tipo del VIDEOJUEGO: en el TCG no existe
        // (el equivalente es "Rayo" / lightning)
        $this->withHeader('Authorization', 'Bearer ' . auth()->login($admin))
             ->postJson('/api/cartas', ['nombre' => 'Pikachu', 'tipo' => 'Eléctrico'])
             ->assertStatus(422);

        $this->assertDatabaseMissing('cartas', ['nombre' => 'Pikachu']);
    }

    // --- Test 5b: Navegación anterior/siguiente acotada al set ---
    // El detalle debe saltar a la siguiente carta DEL MISMO SET aunque
    // haya cartas de otros sets con IDs intermedios
    public function test_navegacion_del_detalle_se_limita_al_set()
    {
        // detalle_synced_at evita que el detalle intente hidratarse
        // contra TCGdex durante el test
        $primera = Carta::create(['nombre' => 'Bulbasaur', 'tcgdex_id' => 'sv03.5-001', 'set_id' => 'sv03.5', 'detalle_synced_at' => now()]);
        Carta::create(['nombre' => 'Ampharos', 'tcgdex_id' => 'neo1-1', 'set_id' => 'neo1', 'detalle_synced_at' => now()]);
        $tercera = Carta::create(['nombre' => 'Ivysaur', 'tcgdex_id' => 'sv03.5-002', 'set_id' => 'sv03.5', 'detalle_synced_at' => now()]);

        $respuesta = $this->getJson("/api/cartas/{$primera->id}");

        $respuesta->assertStatus(200)
                  ->assertJsonPath('siguiente_id', $tercera->id)
                  ->assertJsonPath('anterior_id', null);
    }

    // --- Test 6: Filtrar cartas por tipo ---
    // Comprueba que el filtro por tipo del catálogo funciona correctamente
    public function test_se_puede_filtrar_cartas_por_tipo()
    {
        // Creamos dos cartas de tipos diferentes
        Carta::create(['nombre' => 'Charizard', 'tipo_key' => 'fire',  'rareza_key' => 'rare']);
        Carta::create(['nombre' => 'Gyarados',  'tipo_key' => 'water', 'rareza_key' => 'rare']);

        // Filtramos por tipo Fuego usando el parámetro de la query
        $respuesta = $this->getJson('/api/cartas?tipo=Fuego');

        // Debe devolver 200, exactamente 1 carta en "data" y que sea Charizard
        // (el catálogo responde con el paginador de Laravel)
        $respuesta->assertStatus(200)
                  ->assertJsonCount(1, 'data')
                  ->assertJsonFragment(['nombre' => 'Charizard']);
    }
}