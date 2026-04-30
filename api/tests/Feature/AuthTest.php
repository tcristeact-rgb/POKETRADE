<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // Prueba: registro exitoso
    public function test_usuario_puede_registrarse()
    {
        $respuesta = $this->postJson('/api/auth/registro', [
            'nombre'           => 'Daniel',
            'apellido'         => 'Leal',
            'email'            => 'daniel@test.com',
            'password'         => '123456',
            'fecha_nacimiento' => '2005-12-09',
            'nacionalidad'     => 'Española',
        ]);

        $respuesta->assertStatus(201)
                  ->assertJsonFragment(['mensaje' => 'Usuario registrado correctamente']);

        $this->assertDatabaseHas('users', ['email' => 'daniel@test.com']);
    }

    // Prueba: no se puede registrar con email duplicado
    public function test_no_se_puede_registrar_email_duplicado()
    {
        User::create([
            'nombre'   => 'Daniel',
            'apellido' => 'Leal',
            'email'    => 'daniel@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'cliente',
        ]);

        $respuesta = $this->postJson('/api/auth/registro', [
            'nombre'   => 'Otro',
            'apellido' => 'Usuario',
            'email'    => 'daniel@test.com',
            'password' => '123456',
        ]);

        $respuesta->assertStatus(400);
    }

    // Prueba: login exitoso devuelve token
    public function test_usuario_puede_hacer_login()
    {
        User::create([
            'nombre'   => 'Daniel',
            'apellido' => 'Leal',
            'email'    => 'daniel@test.com',
            'password' => bcrypt('123456'),
            'rol'      => 'cliente',
        ]);

        $respuesta = $this->postJson('/api/auth/login', [
            'email'    => 'daniel@test.com',
            'password' => '123456',
        ]);

        $respuesta->assertStatus(200)
                  ->assertJsonStructure(['token', 'usuario']);
    }

    // Prueba: login con credenciales incorrectas falla
    public function test_login_con_credenciales_incorrectas_falla()
    {
        $respuesta = $this->postJson('/api/auth/login', [
            'email'    => 'noexiste@test.com',
            'password' => 'wrongpassword',
        ]);

        $respuesta->assertStatus(400)
                  ->assertJsonFragment(['error' => 'Email o contraseña incorrectos']);
    }

    // Prueba: registro sin campos obligatorios falla
    public function test_registro_sin_campos_obligatorios_falla()
    {
        $respuesta = $this->postJson('/api/auth/registro', [
            'nombre' => 'Solo nombre',
        ]);

        $respuesta->assertStatus(400);
    }
}