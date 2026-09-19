---
estado: implementada
fecha: 2026-09-19
origen: claude-code
---

# Verificación de correo con código de 6 dígitos, enviado por Brevo

## Problema

Cualquiera puede registrarse con un correo que no es suyo: no se comprueba
nada. Consecuencias: cuentas con direcciones ajenas (y el dueño real no puede
registrarse después, porque `email` es único), tradeos publicados por cuentas
que nadie puede contactar, y sin una base para recuperar contraseñas en el
futuro. Además hoy `MAIL_MAILER=log`: la aplicación no envía ningún correo.

Condiciones del despliegue que descartan el mecanismo estándar de Laravel
(enlace firmado que apunta a la API): la API vive en Render free con cold start
de 20-30 s, así que el primer clic del usuario sería una pestaña en blanco medio
minuto; y **no hay worker de cola**, así que cualquier notificación `ShouldQueue`
se encolaría y no saldría nunca. Análisis de las tres opciones y por qué esta:
ver conversación del 2026-09-19 (opción 2 + Brevo).

## Decisión

Al registrarse, el usuario recibe por correo un **código de 6 dígitos** con
15 minutos de validez y lo escribe en una página nueva del frontend. **Hasta
verificar no puede iniciar sesión**: el login con credenciales correctas de una
cuenta sin verificar responde 403 y el frontend lleva a la página del código.
Ese es el único candado: sin token no hay nada más que proteger. El correo sale
por **Brevo SMTP** (300/día gratis, remitente verificado por código, sin dominio
propio), en la misma petición de registro (sin cola).

### Backend

**Esquema** — migración nueva, sin tocar las existentes:

| Columna (`users`) | Tipo | Para qué |
|---|---|---|
| `email_verified_at` | `timestamp` nullable | el estándar de Laravel; `null` = sin verificar |
| `codigo_verificacion` | `string` nullable | **hash** (`Hash::make`) del código vigente, nunca el código |
| `codigo_expira_en` | `timestamp` nullable | 15 min desde el envío |
| `codigo_intentos` | `unsignedTinyInteger` default 0 | fallos con el código vigente |

Los usuarios que ya existan cuando se aplique la migración quedan marcados
como verificados (`email_verified_at = now()` en el `up()`): son cuentas
anteriores a la regla y no se les puede exigir un correo que nunca se envió.
El `down()` elimina las cuatro columnas.

**`User`**: implementa `MustVerifyEmail` solo por `hasVerifiedEmail()` y
`markEmailAsVerified()`; **no** se usa `sendEmailVerificationNotification()`
de Laravel (es un enlace, y encola). `codigo_verificacion` va en `$hidden`.

**Servicio `App\Services\VerificacionDeCorreo`** (sin lógica en el controlador):
- `enviarCodigo(User $u, string $idioma): void` — genera 6 dígitos con
  `random_int(0, 999999)` con ceros a la izquierda, guarda el hash, la
  caducidad y `codigo_intentos = 0`, y envía `CodigoDeVerificacion` (un
  `Mailable` **sin** `ShouldQueue`) en el idioma pedido.
- `verificar(User $u, string $codigo): ResultadoVerificacion` — enum con
  `ok`, `caducado`, `incorrecto`, `bloqueado`. Comprueba en este orden:
  ya verificado → `ok`; sin código o caducado → `caducado`; `codigo_intentos
  >= 5` → `bloqueado`; `Hash::check` falla → incrementa intentos e
  `incorrecto`; acierto → `markEmailAsVerified()`, borra código, caducidad e
  intentos, `ok`.

**Endpoints** (todos con `throttle:login`, 5/min por email+IP, el limiter que
ya existe):

| Ruta | Público | Entrada | Respuestas |
|---|---|---|---|
| `POST /auth/registro` (existente) | sí | igual | igual (201 + `id`), pero además envía el código. Si Brevo falla, el registro **sigue siendo válido** y se registra un `Log::warning`; el usuario podrá pedir reenvío |
| `POST /auth/login` (existente) | sí | igual | credenciales incorrectas → 401 como hoy; correctas pero **sin verificar** → 403 `{error: mensajes.correo_no_verificado, codigo: 'correo_no_verificado', email}` y **sin token** (la sesión iniciada por `attempt()` se descarta). Se comprueba después de las credenciales: quien no conoce la contraseña no averigua si la cuenta está verificada |
| `POST /auth/verificar` | sí | `email`, `codigo` (6 dígitos) | 200 `{mensaje}` · 422 `{error}` con código incorrecto/caducado/bloqueado, mensaje distinto por caso · 200 también si ya estaba verificado (idempotente) |
| `POST /auth/reenviar-codigo` | sí | `email` | **siempre 200** con el mismo mensaje, exista o no el email y esté o no verificado: no se revela quién está registrado. Solo envía si existe y no está verificado |

Público (sin JWT) porque el usuario acaba de registrarse y todavía no ha
iniciado sesión.

**Sin middleware por ruta**: el candado está en `login()`. Un token solo se
emite a cuentas verificadas (el registro no emite token, y el cambio de
contraseña exige uno), así que ninguna ruta protegida puede recibir una cuenta
sin verificar.

**Correo** — `App\Mail\CodigoDeVerificacion`, plantilla Blade propia
(`resources/views/correo/codigo-verificacion.blade.php`) en ES y EN según el
idioma con que se registró (`Accept-Language` de la petición), texto plano
además de HTML, asunto y cuerpo por `__()` en `lang/{es,en}/correo.php`. Sin
imágenes ni enlaces: "Tu código es **482 913**. Caduca en 15 minutos. Si no has
sido tú, ignora este correo."

**Transporte** — `MAIL_MAILER=smtp`, `MAIL_HOST=smtp-relay.brevo.com`,
`MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`, `MAIL_USERNAME` = login SMTP de Brevo,
`MAIL_PASSWORD` = clave SMTP de Brevo, `MAIL_FROM_ADDRESS` = remitente
verificado en Brevo. Todo por variables de entorno en Render; `.env.example` los
documenta con placeholders. En local, `MAIL_MAILER=log` sigue siendo el valor
por defecto: el correo (con el código) aparece en `storage/logs/laravel.log`.
**`QUEUE_CONNECTION` no cambia**: el `Mailable` no se encola.

### Frontend

- **`pages/verificar.html`** (+ `/en/`): un campo para el código
  (`inputmode="numeric"`, `autocomplete="one-time-code"`, `pattern="[0-9]{6}"`),
  el email prellenado desde `?email=`, botón "Verificar", enlace "Reenviar
  código" con cuenta atrás de 60 s tras usarlo, y mensajes de error por caso.
  Al verificar: a `login.html?verificado=ok` y el login muestra el aviso.
- **`registro.js`**: tras el 201 va a `verificar.html?email=…` (hoy va a
  `login.html?registro=ok`). El aviso de `login.html` para `registro=ok` deja de
  usarse y se retira.
- **`login.js` / `auth.js`**: si el login responde 403 con
  `codigo: 'correo_no_verificado'`, `login()` de `auth.js` no guarda sesión y
  lleva a `verificar.html?email=…&motivo=login`; la página lo explica ("tu
  cuenta aún no está verificada") y ofrece reenviar el código.
- Textos nuevos por `t(...)` en ES y EN (página, avisos, errores, cuenta atrás).
- Sin dependencias ni build, como siempre.

## Alternativas descartadas

- **Enlace firmado de Laravel** (`MustVerifyEmail` completo): el clic aterriza
  en Render dormido; redirección entre dominios; notificación encolada sin
  worker.
- **Enlace con token opaco al frontend**: resuelve el aterrizaje pero es
  reimplementar la opción 1 con una tabla más; no mejora frente a enlaces
  bloqueados por el cliente de correo.
- **Permitir el login y bloquear solo crear tradeos/inventario** (primera
  versión de esta spec): descartado por decisión del propietario, 2026-09-19:
  sin bloquear el login la verificación no sirve de nada. Con el candado en el
  login, además, sobra el middleware por ruta.
- **Código en texto plano en la BD**: un volcado de la BD daría acceso a
  verificar cualquier cuenta. Se guarda el hash, como una contraseña.
- **Enviar desde una cola**: no hay worker; `QUEUE_CONNECTION=sync` habría sido
  una segunda forma de decir "sin cola" con más magia.
- **Resend**: sin dominio propio solo envía al correo del propietario de la
  cuenta. **Gmail SMTP**: funciona, pero ata el envío a una cuenta personal y a
  su contraseña de aplicación.

## Criterios de aceptación

**Backend** (`tests/Feature/VerificacionDeCorreoTest.php`, con `Mail::fake()`)

- [ ] Al registrarse se envía **un** `CodigoDeVerificacion` al email del
      usuario, en el idioma del `Accept-Language` de la petición (asunto en ES y
      en EN comprobados), y `users.codigo_verificacion` es un hash: `Hash::check`
      del código enviado contra él es `true` y la columna no contiene el código.
- [ ] `POST /auth/verificar` con el código correcto → 200,
      `email_verified_at` no nulo, y código/caducidad/intentos a `null`/0.
- [ ] Código incorrecto → 422 con mensaje de "incorrecto" e `codigo_intentos`
      incrementado; al 5º fallo → 422 "bloqueado" y el código correcto ya no
      vale hasta reenviar.
- [ ] Código caducado (`codigo_expira_en` en el pasado) → 422 "caducado".
- [ ] Verificar dos veces → 200 las dos (idempotente).
- [ ] `POST /auth/reenviar-codigo` con email registrado y sin verificar → 200 y
      un correo nuevo; el código anterior deja de valer.
- [ ] `POST /auth/reenviar-codigo` con email inexistente o ya verificado →
      **200 con el mismo mensaje** y ningún correo (`Mail::assertNothingSent`).
- [ ] Si el envío lanza excepción durante el registro, la respuesta sigue siendo
      201 y hay `Log::warning`.
- [ ] Login con credenciales correctas y cuenta **sin verificar** → 403 con
      `codigo: 'correo_no_verificado'` y sin `token` en la respuesta; tras
      verificar, el mismo login → 200 con token. Contraseña incorrecta en una
      cuenta sin verificar → 401 (no se revela el estado de verificación).
- [ ] La migración marca verificados a los usuarios existentes (test: crear
      usuario, ejecutar `up()`… o comprobar que el seeder de local queda
      verificado tras `migrate --seed`).
- [ ] Los 6 endpoints/limiters: 6 peticiones seguidas a `/auth/verificar` con el
      mismo email → 429 (hereda `throttle:login`).
- [ ] `codigo_verificacion` no aparece en `GET /usuario/perfil`.
- [ ] `composer test` en verde. Los tests existentes que crean usuarios con
      `User::create` y luego hacen login por la API pasan a crearlos con
      `email_verified_at`: es el único cambio admisible en tests existentes, y
      es consecuencia directa de la regla nueva, no un ajuste para que pasen.

**Frontend** (`tests-e2e/`, un test; el correo se lee del log local)

- [ ] Registrarse en la web lleva a `verificar.html?email=…`; el código se
      extrae de `api/storage/logs/laravel.log` en el test; introducirlo →
      `login.html?verificado=ok` con el aviso visible.
- [ ] Un código erróneo muestra el mensaje de error sin salir de la página.
- [ ] Iniciar sesión con una cuenta sin verificar lleva a `verificar.html`
      con el aviso de "cuenta sin verificar", no al home.
- [ ] Textos nuevos por `t(...)` en ES y EN; ningún `package.json` bajo `frontend/`.

**Transporte (manual, una vez, en Render)**

- [ ] Cuenta Brevo con remitente verificado; variables `MAIL_*` en Render;
      un registro real recibe el correo en menos de un minuto y el código
      funciona. Se anota en `docs/decisions/` la fecha y el remitente usado.

## Archivos afectados (previsión)

- `api/database/migrations/2026_09_XX_verificacion_de_correo.php` — nueva
- `api/app/Models/User.php` — `MustVerifyEmail`, `$hidden`, `$fillable`
- `api/app/Services/VerificacionDeCorreo.php` — nuevo
- `api/app/Mail/CodigoDeVerificacion.php` + `resources/views/correo/codigo-verificacion.blade.php` — nuevos
- `api/app/Http/Controllers/AuthController.php` — envío en `registro`, `verificar`, `reenviarCodigo`
- `api/routes/api.php` — 2 rutas nuevas
- `api/lang/{es,en}/mensajes.php`, `lang/{es,en}/correo.php` — textos
- `api/.env.example` — bloque `MAIL_*` de Brevo documentado
- `api/tests/Feature/VerificacionDeCorreoTest.php` — nuevo
- `frontend/pages/verificar.html`, `frontend/js/verificar.js` — nuevos
- `frontend/js/registro.js`, `frontend/js/login.js`, `frontend/js/auth.js` — flujo y 403 del login
- `api/tests/Feature/AuthTest.php`, `EndurecerAuthTest.php` — usuarios de prueba verificados
- `frontend/js/i18n/{es,en}.js` — claves nuevas
- `tests-e2e/tests/caminos-criticos.spec.mjs` — un test
- `README.md` — variables de entorno y el flujo

## Fuera de alcance

- Recuperación de contraseña (reutilizará el servicio y la página; spec aparte).
- Cambio de email desde el perfil (hoy el perfil no permite cambiarlo).
- Dominio propio y autenticación DKIM/SPF en Brevo (sin dominio, Brevo sustituye
  el remitente; aceptable para la demo).
- Borrar cuentas no verificadas pasado un tiempo.
- Cola de envío: el día que haya worker, el `Mailable` pasa a `ShouldQueue` con
  una línea.

## Notas de implementación

- El código se genera con `str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT)`:
  `random_int` es CSPRNG; `rand()`/`mt_rand()` no.
- `Hash::make` con `BCRYPT_ROUNDS=12` cuesta ~250 ms por código: aceptable en
  registro y reenvío (van con throttle); en tests son 4 rondas.
- El mensaje de "bloqueado" no debe decir cuántos intentos quedan antes; el de
  "incorrecto" tampoco (evita que sirva de oráculo).
- Un solo `Log::warning` por fallo de envío, con `email` y clase de excepción;
  el registro no debe fallar porque Brevo esté caído.
- `codigo_intentos` se reinicia en cada envío/reenvío, y un reenvío invalida el
  código anterior (se sobreescribe el hash).
- `login.html?verificado=ok` reutiliza el bloque de aviso que hoy usa `registro=ok`.
