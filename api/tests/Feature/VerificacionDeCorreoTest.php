<?php

namespace Tests\Feature;

use App\Mail\CodigoDeVerificacion;
use App\Models\User;
use App\Services\VerificacionDeCorreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

// Verificación de correo por código de 6 dígitos: criterios de
// docs/specs/2026-09-19-verificacion-de-correo.md, uno por test.
class VerificacionDeCorreoTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'nueva@poketrade.es';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function registrar(array $extra = [], array $cabeceras = [])
    {
        return $this->withHeaders($cabeceras)->postJson('/api/auth/registro', $extra + [
            'nombre'   => 'Nueva',
            'apellido' => 'Usuaria',
            'email'    => self::EMAIL,
            'password' => 'secreta123',
        ]);
    }

    // El código que se envió, leído del Mailable capturado por Mail::fake
    private function codigoEnviado(): string
    {
        $codigo = null;
        Mail::assertSent(CodigoDeVerificacion::class, function (CodigoDeVerificacion $m) use (&$codigo) {
            $codigo = $m->codigo;

            return true;
        });

        return $codigo;
    }

    private function verificar(string $codigo, string $email = self::EMAIL)
    {
        return $this->postJson('/api/auth/verificar', ['email' => $email, 'codigo' => $codigo]);
    }

    private function login(string $password = 'secreta123')
    {
        return $this->postJson('/api/auth/login', ['email' => self::EMAIL, 'password' => $password]);
    }

    // ── Registro y envío ─────────────────────────────────────────────────

    public function test_al_registrarse_se_envia_un_codigo_y_en_la_bd_solo_queda_su_hash(): void
    {
        $this->registrar()->assertStatus(201);

        Mail::assertSent(CodigoDeVerificacion::class, 1);
        Mail::assertSent(CodigoDeVerificacion::class, fn ($m) => $m->hasTo(self::EMAIL));

        $codigo  = $this->codigoEnviado();
        $usuario = User::firstWhere('email', self::EMAIL);

        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $codigo);
        $this->assertNull($usuario->email_verified_at);
        $this->assertNotSame($codigo, $usuario->codigo_verificacion);
        $this->assertTrue(Hash::check($codigo, $usuario->codigo_verificacion));
        $this->assertTrue($usuario->codigo_expira_en->isFuture());
    }

    public function test_el_correo_sale_en_el_idioma_de_la_peticion(): void
    {
        $this->registrar(cabeceras: ['Accept-Language' => 'en'])->assertStatus(201);

        Mail::assertSent(CodigoDeVerificacion::class, function (CodigoDeVerificacion $m) {
            return $m->locale === 'en'
                && str_contains($m->envelope()->subject, 'is your PokeTrade verification code');
        });
    }

    public function test_si_el_envio_falla_el_registro_sigue_siendo_valido_y_se_avisa_en_el_log(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP caído'));
        Log::shouldReceive('warning')->once()->withArgs(fn ($msg, $ctx) => $ctx['email'] === self::EMAIL);

        $this->registrar()->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => self::EMAIL]);
    }

    // ── Verificar ────────────────────────────────────────────────────────

    public function test_el_codigo_correcto_verifica_la_cuenta_y_limpia_el_codigo(): void
    {
        $this->registrar();

        $this->verificar($this->codigoEnviado())
             ->assertOk()
             ->assertJsonStructure(['mensaje']);

        $usuario = User::firstWhere('email', self::EMAIL);
        $this->assertNotNull($usuario->email_verified_at);
        $this->assertNull($usuario->codigo_verificacion);
        $this->assertNull($usuario->codigo_expira_en);
        $this->assertSame(0, $usuario->codigo_intentos);
    }

    public function test_un_codigo_incorrecto_devuelve_422_y_cuenta_el_intento(): void
    {
        $this->registrar();
        $bueno = $this->codigoEnviado();
        $malo  = $bueno === '000000' ? '000001' : '000000';

        $this->verificar($malo)->assertStatus(422)->assertJsonPath('error', __('mensajes.codigo_incorrecto'));
        $this->assertSame(1, User::firstWhere('email', self::EMAIL)->codigo_intentos);
    }

    public function test_al_quinto_fallo_el_codigo_queda_bloqueado_aunque_luego_sea_correcto(): void
    {
        $this->registrar();
        $bueno = $this->codigoEnviado();
        $malo  = $bueno === '000000' ? '000001' : '000000';

        for ($i = 0; $i < VerificacionDeCorreo::INTENTOS_MAXIMOS; $i++) {
            $this->verificar($malo)->assertStatus(422);
        }

        $this->verificar($bueno)->assertStatus(422)->assertJsonPath('error', __('mensajes.codigo_bloqueado'));
        $this->assertNull(User::firstWhere('email', self::EMAIL)->email_verified_at);
    }

    public function test_un_codigo_caducado_devuelve_422(): void
    {
        $this->registrar();
        $codigo = $this->codigoEnviado();
        User::firstWhere('email', self::EMAIL)->forceFill(['codigo_expira_en' => now()->subMinute()])->save();

        $this->verificar($codigo)->assertStatus(422)->assertJsonPath('error', __('mensajes.codigo_caducado'));
    }

    public function test_verificar_dos_veces_es_idempotente(): void
    {
        $this->registrar();
        $codigo = $this->codigoEnviado();

        $this->verificar($codigo)->assertOk();
        $this->verificar($codigo)->assertOk();
        $this->verificar('123456')->assertOk();   // ya verificada: cualquier código da 200
    }

    public function test_un_email_inexistente_se_trata_como_codigo_incorrecto(): void
    {
        $this->verificar('123456', 'nadie@poketrade.es')
             ->assertStatus(422)
             ->assertJsonPath('error', __('mensajes.codigo_incorrecto'));
    }

    public function test_el_codigo_debe_ser_de_seis_digitos(): void
    {
        $this->registrar();

        $this->verificar('12345')->assertStatus(422);
        $this->verificar('abcdef')->assertStatus(422);
    }

    // ── Reenviar ─────────────────────────────────────────────────────────

    public function test_reenviar_manda_un_codigo_nuevo_y_el_anterior_deja_de_valer(): void
    {
        $this->registrar();
        $primero = $this->codigoEnviado();

        $this->postJson('/api/auth/reenviar-codigo', ['email' => self::EMAIL])->assertOk();

        Mail::assertSent(CodigoDeVerificacion::class, 2);
        $usuario = User::firstWhere('email', self::EMAIL);
        // Con un millón de códigos posibles, que el nuevo coincida con el
        // anterior es despreciable; si pasa, este assert lo dirá
        $this->assertFalse(Hash::check($primero, $usuario->codigo_verificacion), 'el reenvío no cambió el código');
        $this->assertSame(0, $usuario->codigo_intentos);
    }

    public function test_reenviar_responde_igual_exista_o_no_el_email_y_no_envia_nada_si_no_toca(): void
    {
        $this->registrar();
        $this->verificar($this->codigoEnviado())->assertOk();
        $enviados = 1;

        $verificada  = $this->postJson('/api/auth/reenviar-codigo', ['email' => self::EMAIL]);
        $inexistente = $this->postJson('/api/auth/reenviar-codigo', ['email' => 'nadie@poketrade.es']);

        $verificada->assertOk();
        $inexistente->assertOk();
        $this->assertSame($verificada->json('mensaje'), $inexistente->json('mensaje'));
        Mail::assertSent(CodigoDeVerificacion::class, $enviados);   // ninguno nuevo
    }

    // ── Login ────────────────────────────────────────────────────────────

    public function test_sin_verificar_el_login_devuelve_403_sin_token_y_tras_verificar_200(): void
    {
        $this->registrar();

        $res = $this->login();
        $res->assertStatus(403)
            ->assertJsonPath('codigo', 'correo_no_verificado')
            ->assertJsonPath('email', self::EMAIL)
            ->assertJsonMissingPath('token');

        $this->verificar($this->codigoEnviado())->assertOk();

        $this->login()->assertOk()->assertJsonStructure(['token']);
    }

    public function test_la_contrasena_incorrecta_no_revela_si_la_cuenta_esta_verificada(): void
    {
        $this->registrar();

        $this->login('otra')->assertStatus(401);
    }

    // ── Otros ────────────────────────────────────────────────────────────

    public function test_los_usuarios_de_demo_del_seeder_nacen_verificados(): void
    {
        // Los usuarios que ya existían al migrar los marca la migración (se
        // comprobó a mano sobre la BD de desarrollo: 3 de 3). Los del seeder se
        // crean DESPUÉS de migrar, sin pasar por el registro: si no nacieran
        // verificados, las cuentas de demo no podrían iniciar sesión en local.
        //
        // El seeder solo siembra en entorno local (guard deliberado): se fija
        // ese entorno solo durante este test.
        $this->app['env'] = 'local';
        $this->seed();

        $this->assertGreaterThan(0, User::count());
        $this->assertSame(0, User::whereNull('email_verified_at')->count());
    }

    public function test_los_datos_del_codigo_no_se_exponen_en_el_perfil(): void
    {
        $this->registrar();
        $this->verificar($this->codigoEnviado())->assertOk();
        $token = $this->login()->json('token');

        $this->withToken($token)->getJson('/api/usuario/perfil')
             ->assertOk()
             ->assertJsonMissingPath('codigo_verificacion')
             ->assertJsonMissingPath('codigo_expira_en')
             ->assertJsonMissingPath('codigo_intentos');
    }

    public function test_verificar_tiene_su_propio_limite_de_diez_por_minuto(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->verificar('000000')->assertStatus(422);
        }

        $this->verificar('000000')->assertStatus(429);
    }
}
