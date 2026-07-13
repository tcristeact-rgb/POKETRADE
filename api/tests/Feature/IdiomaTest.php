<?php

namespace Tests\Feature;

use App\Models\Carta;
use App\Models\Tradeo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Negociación de idioma (middleware EstablecerIdioma) y traducción de los
// mensajes de la API. Importa porque el frontend pinta el campo 'error' de la
// respuesta tal cual: si el backend contesta en español a un usuario inglés,
// el usuario ve español.
class IdiomaTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_mensajes_salen_en_el_idioma_pedido(): void
    {
        $this->withHeader('Accept-Language', 'es')
            ->getJson('/api/cartas/999999')
            ->assertStatus(404)
            ->assertJson(['error' => 'Carta no encontrada']);

        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/cartas/999999')
            ->assertStatus(404)
            ->assertJson(['error' => 'Card not found']);
    }

    public function test_los_mensajes_de_validacion_tambien_se_traducen(): void
    {
        // El nombre del campo sale del array 'attributes': sin él, el usuario
        // inglés leería "The nombre field is required", con el nombre de la
        // columna de la BD asomando por la interfaz
        $this->withHeader('Accept-Language', 'en')
            ->postJson('/api/auth/registro', [])
            ->assertStatus(422)
            ->assertJson(['error' => 'The first name field is required.']);

        $this->withHeader('Accept-Language', 'es')
            ->postJson('/api/auth/registro', [])
            ->assertStatus(422)
            ->assertJson(['error' => 'El campo nombre es obligatorio.']);
    }

    public function test_sin_cabecera_responde_en_el_idioma_por_defecto(): void
    {
        // withHeaders() reemplaza las cabeceras: así se anula la que fija
        // TestCase y se simula un cliente que no pide ningún idioma
        $this->withHeaders(['Accept-Language' => ''])
            ->getJson('/api/cartas/999999')
            ->assertJson(['error' => 'Carta no encontrada'])
            ->assertHeader('Content-Language', 'es');
    }

    public function test_un_idioma_que_no_hablamos_cae_al_por_defecto(): void
    {
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->getJson('/api/cartas/999999')
            ->assertJson(['error' => 'Carta no encontrada'])
            ->assertHeader('Content-Language', 'es');
    }

    public function test_se_respeta_el_orden_de_preferencia_q(): void
    {
        // El francés va primero pero no lo hablamos; el inglés es el siguiente
        // que sí, aunque su q sea menor
        $this->withHeader('Accept-Language', 'fr;q=1.0,en;q=0.8')
            ->getJson('/api/cartas/999999')
            ->assertHeader('Content-Language', 'en');

        // Aquí el inglés gana al español por peso, aunque el español vaya antes
        $this->withHeader('Accept-Language', 'es;q=0.5,en;q=0.9')
            ->getJson('/api/cartas/999999')
            ->assertHeader('Content-Language', 'en');
    }

    public function test_la_variante_regional_cuenta_como_su_idioma(): void
    {
        // "en-GB" es inglés: solo miramos la etiqueta principal
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')
            ->getJson('/api/cartas/999999')
            ->assertJson(['error' => 'Card not found']);
    }

    public function test_la_respuesta_avisa_de_que_varia_con_el_idioma(): void
    {
        // Sin Vary, una caché intermedia podría servirle a un usuario inglés
        // la respuesta que guardó para uno español
        $respuesta = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/cartas/999999');

        $respuesta->assertHeader('Content-Language', 'en');
        $this->assertStringContainsString(
            'Accept-Language',
            $respuesta->headers->get('Vary')
        );
    }

    public function test_el_nombre_de_la_carta_no_se_traduce_en_los_mensajes(): void
    {
        // El nombre de la carta es un DATO y viaja como parámetro (:carta):
        // la frase se traduce, el nombre se queda como está.
        $creador   = $this->crearUsuario('creador@test.com');
        $aceptante = $this->crearUsuario('aceptante@test.com');

        $pedida    = Carta::create(['nombre' => 'Charizard ex']);
        $ofrecida  = Carta::create(['nombre' => 'Pikachu']);

        $tradeo = Tradeo::create(['user_id' => $creador->id, 'estado' => 'activo']);
        $tradeo->cartasBusca()->attach($pedida->id);
        $tradeo->cartasOfrece()->attach($ofrecida->id);

        // El aceptante no tiene la carta requerida en su inventario
        $this->actingAs($aceptante, 'api')
            ->withHeader('Accept-Language', 'en')
            ->postJson("/api/tradeos/{$tradeo->id}/aceptar")
            ->assertStatus(422)
            ->assertJson(['error' => 'You do not have the required card: Charizard ex']);
    }

    private function crearUsuario(string $email): User
    {
        return User::create([
            'nombre'   => 'Test',
            'apellido' => 'Usuario',
            'email'    => $email,
            'password' => bcrypt('123456'),
            'rol'      => 'cliente',
        ]);
    }
}
