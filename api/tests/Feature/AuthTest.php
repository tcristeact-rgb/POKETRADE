<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase; // Resetea la BD entre cada test
use Tests\TestCase;
use App\Models\User;

class AuthTest extends TestCase
{
    // RefreshDatabase garantiza que cada test empieza con la BD limpia
    // Así los tests son independientes entre sí y no se interfieren
    use RefreshDatabase;

    // --- Test 1: Registro exitoso ---
    // Comprueba que un usuario puede registrarse correctamente
    // y que se guarda en la base de datos
    public function test_usuario_puede_registrarse()
    {
        // Hacemos una petición POST al endpoint de registro con datos válidos
        $respuesta = $this->postJson('/api/auth/registro', [
            'nombre'           => 'Daniel',
            'apellido'         => 'Leal',
            'email'            => 'daniel@test.com',
            'password'         => '123456',
            'fecha_nacimiento' => '2005-12-09',
            'nacionalidad'     => 'Española',
        ]);

        // Verificamos que la respuesta es 201 (creado correctamente)
        // y que el JSON contiene el mensaje de éxito
        $respuesta->assertStatus(201)
                  ->assertJsonFragment(['mensaje' => 'Usuario registrado correctamente']);

        // Verificamos que el usuario se ha guardado realmente en la base de datos
        $this->assertDatabaseHas('users', ['email' => 'daniel@test.com']);
    }

    // --- Test 2: Email duplicado ---
    // Comprueba que no se puede registrar dos usuarios con el mismo email
    public function test_no_se_puede_registrar_email_duplicado()
    {
        // Creamos un usuario con ese email directamente en la BD
        User::create([
            'nombre'   => 'Daniel',
            'apellido' => 'Leal',
            'email'    => 'daniel@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'cliente',
        ]);

        // Intentamos registrar otro usuario con el mismo email
        $respuesta = $this->postJson('/api/auth/registro', [
            'nombre'   => 'Otro',
            'apellido' => 'Usuario',
            'email'    => 'daniel@test.com', // Email ya existente
            'password' => '123456',
        ]);

        // Debe devolver 400 (error de validación)
        $respuesta->assertStatus(400);
    }

    // --- Test 3: Login exitoso ---
    // Comprueba que un usuario puede hacer login y recibe un token JWT
    public function test_usuario_puede_hacer_login()
    {
        // Creamos el usuario directamente en la BD
        User::create([
            'nombre'   => 'Daniel',
            'apellido' => 'Leal',
            'email'    => 'daniel@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'cliente',
        ]);

        // Hacemos la petición de login con las credenciales correctas
        $respuesta = $this->postJson('/api/auth/login', [
            'email'    => 'daniel@test.com',
            'password' => '123456',
        ]);

        // Verificamos que la respuesta es 200 y contiene token y datos del usuario
        $respuesta->assertStatus(200)
                  ->assertJsonStructure(['token', 'usuario']);
    }

    // --- Test 4: Login con credenciales incorrectas ---
    // Comprueba que el login falla si el email o la contraseña son incorrectos
    public function test_login_con_credenciales_incorrectas_falla()
    {
        // Intentamos login con un email que no existe en la BD
        $respuesta = $this->postJson('/api/auth/login', [
            'email'    => 'noexiste@test.com',
            'password' => 'wrongpassword',
        ]);

        // Debe devolver 400 con el mensaje de error correspondiente
        $respuesta->assertStatus(400)
                  ->assertJsonFragment(['error' => 'Email o contraseña incorrectos']);
    }

    // --- Test 5: Registro sin campos obligatorios ---
    // Comprueba que el registro falla si faltan campos requeridos
    public function test_registro_sin_campos_obligatorios_falla()
    {
        // Enviamos solo el nombre, faltan email y password que son obligatorios
        $respuesta = $this->postJson('/api/auth/registro', [
            'nombre' => 'Solo nombre',
        ]);

        // Debe devolver 400 (error de validación)
        $respuesta->assertStatus(400);
    }
}