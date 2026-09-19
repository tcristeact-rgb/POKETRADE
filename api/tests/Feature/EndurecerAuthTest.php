<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// Un test por criterio de docs/specs/2026-09-18-endurecer-auth.md.
//
// Sobre el rate limiter y los tests: cada test arranca con una aplicación nueva
// (refreshApplication) y, con CACHE_STORE=array, un store nuevo. El contador del
// limiter nace a cero en cada test sin que haya que limpiarlo — y dentro de un
// mismo test sí se acumula, que es lo que necesitan los tests del 429.
class EndurecerAuthTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(string $email = 'ana@poketrade.es', string $password = 'secreta123'): User
    {
        return User::create([
            'nombre'   => 'Ana',
            'apellido' => 'Prueba',
            'email'    => $email,
            'password' => Hash::make($password),
            'rol'      => 'cliente',
        ]);
    }

    private function login(string $email, string $password = 'incorrecta')
    {
        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password]);
    }

    private function registro(array $campos = [])
    {
        return $this->postJson('/api/auth/registro', $campos + [
            'nombre'   => 'Nuevo',
            'apellido' => 'Usuario',
            'email'    => 'nuevo@poketrade.es',
            'password' => 'secreta123',
        ]);
    }

    // ── Rate limiting ──────────────────────────────────────────────────────

    public function test_sexto_login_seguido_con_el_mismo_email_devuelve_429(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->login('ana@poketrade.es')->assertStatus(401);
        }

        $this->login('ana@poketrade.es')
             ->assertStatus(429)
             ->assertHeader('Retry-After');
    }

    public function test_el_limite_de_login_es_por_email_y_no_bloquea_a_otros_emails(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->login('ana@poketrade.es')->assertStatus(401);
        }

        // Misma IP, otro email: no hereda el bloqueo
        $this->login('bea@poketrade.es')->assertStatus(401);
    }

    public function test_el_limite_de_login_normaliza_el_email(): void
    {
        // Mayúsculas y espacios no sirven para esquivar el contador
        foreach (['ana@poketrade.es', 'ANA@poketrade.es', ' ana@poketrade.es ', 'Ana@Poketrade.es', 'ana@poketrade.es'] as $email) {
            $this->login($email)->assertStatus(401);
        }

        $this->login('ana@poketrade.es')->assertStatus(429);
    }

    public function test_el_registro_comparte_el_limite_de_login(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->registro(['email' => 'ana@poketrade.es', 'password' => 'x'])->assertStatus(422);
        }

        $this->registro(['email' => 'ana@poketrade.es'])->assertStatus(429);
    }

    public function test_buscar_se_limita_por_ip_a_partir_de_la_peticion_31(): void
    {
        Http::fake(['api.tcgdex.net/*' => Http::response([])]); // TCGdex devuelve un array plano

        for ($i = 1; $i <= 30; $i++) {
            $this->getJson('/api/cartas/buscar?q=pikachu')->assertStatus(200);
        }

        $this->getJson('/api/cartas/buscar?q=pikachu')->assertStatus(429);
    }

    public function test_las_rutas_de_la_api_anuncian_el_limite_general(): void
    {
        $this->getJson('/api/series')
             ->assertOk()
             ->assertHeader('X-RateLimit-Limit', '120');
    }

    // ── Validación de login ────────────────────────────────────────────────

    public function test_login_sin_password_devuelve_422_y_no_500(): void
    {
        $this->crearUsuario();

        $this->postJson('/api/auth/login', ['email' => 'ana@poketrade.es'])
             ->assertStatus(422)
             ->assertJsonStructure(['error']);
    }

    public function test_login_con_password_como_array_devuelve_422_y_no_500(): void
    {
        $this->crearUsuario();

        $this->postJson('/api/auth/login', ['email' => 'ana@poketrade.es', 'password' => ['x']])
             ->assertStatus(422);
    }

    public function test_login_con_email_como_array_devuelve_422_y_no_500(): void
    {
        // El limiter corre antes que el validador y también recibe el array
        $this->postJson('/api/auth/login', ['email' => ['x'], 'password' => 'secreta123'])
             ->assertStatus(422);
    }

    public function test_login_correcto_sigue_funcionando(): void
    {
        $this->crearUsuario();

        $this->login('ana@poketrade.es', 'secreta123')
             ->assertOk()
             ->assertJsonStructure(['token', 'usuario' => ['id', 'nombre', 'apellido', 'email']]);
    }

    // ── Política de contraseñas ────────────────────────────────────────────

    public function test_registro_con_password_de_7_caracteres_devuelve_422(): void
    {
        $this->registro(['password' => 'abcdefg'])->assertStatus(422);
        $this->registro(['password' => 'abcdefgh'])->assertStatus(201);
    }

    public function test_cambio_a_password_de_7_caracteres_devuelve_422(): void
    {
        $usuario = $this->crearUsuario();

        $this->actingAs($usuario, 'api')
             ->putJson('/api/usuario/password', ['password_actual' => 'secreta123', 'password_nuevo' => 'abcdefg'])
             ->assertStatus(422);
    }

    // ── Token tras cambiar la contraseña (punto 4) ─────────────────────────

    public function test_tras_cambiar_la_password_el_token_anterior_devuelve_401(): void
    {
        $this->crearUsuario();
        $tokenViejo = $this->login('ana@poketrade.es', 'secreta123')->json('token');

        $respuesta = $this->withToken($tokenViejo)
             ->putJson('/api/usuario/password', ['password_actual' => 'secreta123', 'password_nuevo' => 'nueva12345'])
             ->assertOk()
             ->assertJsonStructure(['mensaje', 'token']);

        $tokenNuevo = $respuesta->json('token');
        $this->assertNotSame($tokenViejo, $tokenNuevo);

        $this->comoPeticionNueva();
        $this->withToken($tokenViejo)->getJson('/api/usuario/perfil')->assertStatus(401);

        $this->comoPeticionNueva();
        $this->withToken($tokenNuevo)->getJson('/api/usuario/perfil')->assertOk();
    }

    // En el servidor cada petición parsea su propia cabecera Authorization. En el
    // test todas comparten la app, y tanto el JWTGuard como Tymon\JWTAuth\JWT son
    // singletons que se quedan con el token y el usuario de la petición anterior
    // y no vuelven a mirar la cabecera. Sin este reset, el token viejo daría 200
    // (o el nuevo 401) por artefacto, no por lo que hace el código.
    private function comoPeticionNueva(): void
    {
        $this->app['tymon.jwt']->unsetToken();
        $this->app['auth']->forgetGuards();
    }

    // ── Validación de campos ───────────────────────────────────────────────

    public function test_perfil_con_avatar_url_javascript_devuelve_422(): void
    {
        $usuario = $this->crearUsuario();

        foreach (['javascript:alert(1)', 'javascript://alert(1)', 'data:text/html,hola'] as $url) {
            $this->actingAs($usuario, 'api')
                 ->putJson('/api/usuario/perfil', ['avatar_url' => $url])
                 ->assertStatus(422);
        }

        $this->actingAs($usuario, 'api')
             ->putJson('/api/usuario/perfil', ['avatar_url' => 'https://example.com/ana.png'])
             ->assertOk();

        $this->assertSame('https://example.com/ana.png', $usuario->fresh()->avatar_url);
    }

    public function test_perfil_admite_borrar_el_avatar(): void
    {
        $usuario = $this->crearUsuario();
        $usuario->update(['avatar_url' => 'https://example.com/ana.png']);

        $this->actingAs($usuario, 'api')
             ->putJson('/api/usuario/perfil', ['avatar_url' => null])
             ->assertOk();

        $this->assertNull($usuario->fresh()->avatar_url);
    }

    public function test_registro_con_email_de_300_caracteres_devuelve_422(): void
    {
        $email = str_repeat('a', 290).'@x.es'; // 295 > 255

        $this->registro(['email' => $email])->assertStatus(422);
    }

    // ── Enumeración por tiempo ─────────────────────────────────────────────

    public function test_login_con_email_inexistente_paga_el_coste_de_bcrypt(): void
    {
        // No se puede asertar igualdad de tiempos. Sí que la ruta "email no existe"
        // tarda al menos lo que tarda un Hash::check real con las rondas de test
        // (cota inferior): si bcrypt no se ejecutara, la respuesta sería más rápida.
        // El hash dummy del controlador es de coste 12 fijo, así que en la práctica
        // esta petición tarda ~250 ms sea cual sea BCRYPT_ROUNDS.
        $inicio = hrtime(true);
        Hash::check('cualquiera', Hash::make('otra'));
        $costeBcrypt = hrtime(true) - $inicio;

        // Calentamos la aplicación con una petición que no toca bcrypt
        $this->postJson('/api/auth/login', ['email' => 'no', 'password' => 'x'])->assertStatus(422);

        $inicio = hrtime(true);
        $this->login('nadie@poketrade.es', 'cualquiera')->assertStatus(401);
        $duracion = hrtime(true) - $inicio;

        $this->assertGreaterThanOrEqual($costeBcrypt, $duracion);
    }
}
