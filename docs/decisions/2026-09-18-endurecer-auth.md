---
spec: docs/specs/2026-09-18-endurecer-auth.md
fecha: 2026-09-18
resultado: completo
---

# Endurecer la autenticación: rate limiting, validación y ciclo de vida del token

## Qué se implementó

Los seis puntos de la spec, backend y frontend, con un test por criterio en
`tests/Feature/EndurecerAuthTest.php` (17 tests).

- **Rate limiting**: tres limiters en `AppServiceProvider` (`api` 120/min por
  usuario o IP, `login` 5/min por email normalizado + IP, `buscar` 30/min por
  IP), `throttleApi()` en `bootstrap/app.php`, `throttle:login` en
  `/auth/login` y `/auth/registro`, `throttle:buscar` en `/cartas/buscar`.
- **Login valida antes de `attempt()`**: `email required|string|email|max:255`,
  `password required|string` → 422 con el formato de error de la API.
- **Contraseña mínima 8** en registro y cambio de contraseña. Seeder y
  `AuthTest` actualizados a `12345678`.
- **Hash dummy** de coste 12 en `AuthController::HASH_DUMMY`, comprobado cuando
  `attempt()` falla y el email no existe.
- **`email` max:255** en registro; **`avatar_url` `nullable|url:http,https|max:2048`**
  en perfil.
- **Token rotado al cambiar contraseña**: `auth()->invalidate()` +
  `auth()->tokenById()`; la respuesta trae `token`. `auth.js` exporta
  `cambiarPassword()` que lo guarda con `guardarSesion()`; `perfil.js` la usa.
- **Frontend a mínimo 8**: `registro.js`, `perfil.js`, `registro.html`
  (`minlength="8"`), `perfil.html` (placeholder) y las claves i18n ES/EN
  renombradas `min6`→`min8`, `minimo6`→`minimo8`, `passwordMin6`→`passwordMin8`.

## Desviaciones respecto a la spec

1. **Los puntos 4 y 3-frontend se implementaron en dos fases**: primero todo lo
   que no tocaba `frontend/` fuera de `auth.js`, parada, aprobación del usuario
   ("con ambos"), y después el resto. El backend del punto 4 se retuvo hasta el
   OK a propósito: invalidar el token sin que `perfil.js` guardara el nuevo
   habría dejado al usuario en el login con un "sesión expirada" falso.
2. **`tokenById()` en vez de `login()`** para emitir el token nuevo: `login()`
   además muta el estado del guard (usuario y token cacheados), que no hace
   falta para el resto de esa petición y que, en tests, enmascara el resultado
   (ver 8).
3. **Criterio "registro con `avatar_url=javascript:` → 422" corregido a
   `PUT /usuario/perfil`**: `registro()` no acepta ese campo (lo ignora). Se
   añadió además `javascript://` y `data:` al test porque la regla `url` a
   secas los dejaría pasar; por eso es `url:http,https` y no `url`.
4. **"min:8 rompe el seeder" era falso**: el seeder hace `Hash::make()` directo y
   el login no valida longitud. Se cambió igualmente a `12345678` por coherencia.
   Las contraseñas `bcrypt('123456')` de los otros tests (`CartaTest`,
   `TradeoTest`…) se dejan: son fixtures que nunca pasan por el validador ni
   hacen login por la API.
5. **Sin `RateLimiter::clear()` en `TestCase`**: `refreshApplication()` crea una
   aplicación —y un `ArrayStore`— nuevos por test, así que el limiter nace a cero
   cada vez. Verificado empíricamente: la suite completa pasa con el throttle
   activo en testing y sin limpiar nada. Añadir la llamada sería código sin efecto.
6. **Limiter `api` a 120/min y no 60** (el valor por defecto de Laravel): la
   portada dispara tres llamadas en paralelo y una demo desde una misma red
   comparte IP. El encargo no fijaba número.
7. **Test de timing**: no se puede asertar igualdad de latencias. El test
   comprueba una cota inferior (la respuesta con email inexistente tarda al menos
   lo que un `Hash::check` real). Es una prueba de que bcrypt se ejecuta, no de
   que los tiempos sean indistinguibles.
8. **El test del punto 4 resetea `JWTGuard` y `Tymon\JWTAuth\JWT` entre
   peticiones** (`comoPeticionNueva()`: `tymon.jwt->unsetToken()` +
   `auth->forgetGuards()`). En el servidor cada petición parsea su cabecera;
   en el test todas comparten la app y esos dos singletons se quedan con el
   token/usuario anterior. Sin el reset, el token viejo daba 200 por artefacto
   (verificado: falló así dos veces antes de entender la causa).
9. **`frontend/js/perfil.js` normalizado a LF.** El archivo commiteado estaba en
   CRLF con dos `\r\r\n` (en `method: 'PUT',`), y ese CR huérfano hace que git
   lo trate como binario (`git ls-files --eol` → `i/-text`). Todo el resto del
   repo es LF. El `git diff` sale como archivo entero; el cambio real se ve con
   `git diff --text --ignore-cr-at-eol frontend/js/perfil.js`. Lo mismo les pasa
   a `inventario.js` y `publicar-tradeo.js`, que no se han tocado.

## Archivos tocados

- `api/app/Providers/AppServiceProvider.php` — limiters `api`, `login`, `buscar`
- `api/bootstrap/app.php` — `$middleware->throttleApi()`
- `api/routes/api.php` — `throttle:login` en auth, `throttle:buscar` en buscar
- `api/app/Http/Controllers/AuthController.php` — validación previa en login, `min:8`, `email max:255`, `HASH_DUMMY`
- `api/app/Http/Controllers/UsuarioController.php` — `min:8`, `avatar_url url:http,https|max:2048`
- `api/database/seeders/UsuariosSeeder.php` — `123456` → `12345678`
- `api/tests/Feature/AuthTest.php` — `123456` → `12345678`
- `api/tests/Feature/EndurecerAuthTest.php` — nuevo, 17 tests
- `frontend/js/auth.js` — `cambiarPassword()` exportada
- `frontend/js/perfil.js` — usa `cambiarPasswordApi()`, mínimo 8; normalizado a LF
- `frontend/js/registro.js` — mínimo 8
- `frontend/js/i18n/es.js`, `frontend/js/i18n/en.js` — textos y claves a 8
- `frontend/pages/registro.html`, `frontend/pages/perfil.html` — `minlength`, ayuda y placeholder a 8

## Verificación

`composer test` → **113 passed (545 assertions)**. Antes: 96.

- [x] 6º login seguido con el mismo email → 429 — `test_sexto_login_seguido_con_el_mismo_email_devuelve_429` (y `Retry-After` presente)
- [x] Límite por email+IP: otro email no se bloquea — `test_el_limite_de_login_es_por_email_y_no_bloquea_a_otros_emails`
- [x] El email se normaliza (mayúsculas/espacios no esquivan el contador) — `test_el_limite_de_login_normaliza_el_email`
- [x] Registro comparte el limiter — `test_el_registro_comparte_el_limite_de_login`
- [x] `/cartas/buscar` → 429 en la petición 31 — `test_buscar_se_limita_por_ip_a_partir_de_la_peticion_31`
- [x] Cabecera `X-RateLimit-Limit: 120` en rutas generales — `test_las_rutas_de_la_api_anuncian_el_limite_general`
- [x] Login sin `password` → 422 — `test_login_sin_password_devuelve_422_y_no_500`
- [x] Login con `password` array → 422 — `test_login_con_password_como_array_devuelve_422_y_no_500`
- [x] Login con `email` array → 422 (el limiter no revienta) — `test_login_con_email_como_array_devuelve_422_y_no_500`
- [x] Login correcto sigue funcionando — `test_login_correcto_sigue_funcionando`
- [x] Registro con 7 caracteres → 422, con 8 → 201 — `test_registro_con_password_de_7_caracteres_devuelve_422`
- [x] Cambio a 7 caracteres → 422 — `test_cambio_a_password_de_7_caracteres_devuelve_422`
- [x] Token anterior → 401 tras cambiar contraseña; el nuevo → 200; son distintos — `test_tras_cambiar_la_password_el_token_anterior_devuelve_401`
- [x] **Flujo completo con el `auth.js` real** (no simulado): Node con shims de `localStorage`/`window` cargando `frontend/js/auth.js` contra `php artisan serve` + BD SQLite de desarrollo (`CACHE_STORE=database`, blacklist en tabla): registro 201 → `login()` guarda token → `cambiarPassword()` devuelve token, lo guarda, conserva `usuario` → `apiFetch('/usuario/perfil')` 200 con el nuevo → token viejo 401 → login con contraseña nueva 200, con la vieja 401. La extensión de Chrome no estaba conectada, por eso no fue clic a clic. Usuario sonda borrado de la BD local después.
- [x] `node --check` en `auth.js`, `perfil.js`; ninguna referencia a claves i18n `*6` huérfana (`grep`), y cada clave nueva definida en ES y EN.
- [x] `PUT /usuario/perfil` con `avatar_url=javascript:` (y `javascript://`, `data:`) → 422; https válido → 200 — `test_perfil_con_avatar_url_javascript_devuelve_422`
- [x] Borrar el avatar (`null`) sigue permitido — `test_perfil_admite_borrar_el_avatar`
- [x] Email de 295 caracteres → 422 — `test_registro_con_email_de_300_caracteres_devuelve_422`
- [x] Email inexistente paga bcrypt — `test_login_con_email_inexistente_paga_el_coste_de_bcrypt`
- [x] **Limiter con el driver de Render**: `CACHE_STORE=database vendor/bin/phpunit --filter 'test_sexto_login|test_buscar_se_limita|test_las_rutas'` → OK (3 tests, 41 assertions); `tinker` confirma que el limiter usa `Illuminate\Cache\DatabaseStore`. La tabla `cache` la crea `0001_01_01_000001_create_cache_table.php` y `app:arrancar` migra al arrancar.
- [x] Avatares existentes: BD local sin ninguno. Producción no accesible desde aquí; la regla solo se evalúa al guardar el perfil.

## Pendiente

- Revisar el 30/min de `buscar` cuando se implemente el autocompletado.
- `inventario.js` y `publicar-tradeo.js` tienen el mismo CR huérfano que tenía
  `perfil.js` y git los trata como binarios; normalizarlos cuando se toquen.
- Usuarios ya registrados con contraseñas de 6-7 caracteres siguen pudiendo
  entrar (el login no valida longitud, a propósito); solo se les exige 8 al
  cambiarla.
