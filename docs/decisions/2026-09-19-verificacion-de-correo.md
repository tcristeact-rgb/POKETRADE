---
spec: docs/specs/2026-09-19-verificacion-de-correo.md
fecha: 2026-09-19
resultado: completo
---

# Verificación de correo con código de 6 dígitos, enviado por Brevo

## Qué se implementó

- **Migración** `2026_09_19_120000_verificacion_de_correo`: `email_verified_at`,
  `codigo_verificacion` (hash), `codigo_expira_en`, `codigo_intentos`; marca
  verificados a los usuarios existentes (comprobado en la BD de desarrollo:
  3 de 3).
- **`User`** implementa `MustVerifyEmail` (solo `hasVerifiedEmail()` /
  `markEmailAsVerified()`); los tres campos del código en `$hidden`;
  `email_verified_at` en `$fillable` y con cast `datetime`.
- **`VerificacionDeCorreo`** (servicio) + enum `ResultadoVerificacion`:
  `enviarCodigo()` genera con `random_int`, guarda el hash, 15 min, intentos a
  0, y envía síncrono; si el envío lanza, `Log::warning` y devuelve `false`.
  `verificar()` en el orden de la spec (verificado → caducado → bloqueado al 5º
  → incorrecto/ok).
- **`CodigoDeVerificacion`** (Mailable sin `ShouldQueue`), plantilla HTML y de
  texto en `resources/views/correo/`, textos en `lang/{es,en}/correo.php`,
  idioma con `->locale()` desde `Accept-Language`.
- **`AuthController`**: `registro()` envía el código; `login()` responde 403
  `codigo: 'correo_no_verificado'` sin token si la cuenta no está verificada
  (después de comprobar credenciales, `Auth::logout()` descarta el token);
  `verificar()` y `reenviarCodigo()` nuevos.
- **Rutas**: `POST /auth/verificar` (`throttle:verificar`, 10/min) y
  `POST /auth/reenviar-codigo` (`throttle:login`).
- **Frontend**: `pages/verificar.html` + `js/verificar.js` (código con
  `autocomplete="one-time-code"`, reenvío con cuenta atrás de 60 s, aviso con
  `?motivo=login`); `registro.js` → verificar; `auth.js` exporta
  `verificarCorreo()` / `reenviarCodigo()` y `login()` redirige en el 403;
  `login.html` muestra "Correo verificado" con `?verificado=ok`; i18n ES/EN.
- **Seeder**: los usuarios de demo nacen verificados.
- **`.env.example`**: bloque `MAIL_*` de Brevo documentado; `README.md` y
  `tests-e2e/README.md` actualizados.
- **Tests**: `VerificacionDeCorreoTest` (17, con `Mail::fake`) y
  `tests-e2e/tests/verificacion-correo.spec.mjs` (2) con `global-setup.mjs`.

## Desviaciones respecto a la spec

1. **El login se bloquea hasta verificar** (decisión del propietario, tomada
   antes de implementar; la primera versión de la spec permitía el login y
   bloqueaba solo crear). Con eso, el middleware por ruta previsto al principio
   no existe: el único candado está en `login()`. La spec se actualizó antes
   de implementar (aún no había código), no después.
2. **`/auth/verificar` tiene limiter propio (`verificar`, 10/min por email+IP)**
   en vez de `throttle:login`. Con el limiter de login (5/min), compartido con
   el registro que el usuario acaba de hacer, se equivocaba 4 veces con el
   código y recibía un 429 antes que el "código bloqueado" de la spec. La
   fuerza bruta la corta el tope de 5 intentos por código, no el throttle. El
   reenvío sí lleva `throttle:login`.
3. **El seeder marca verificados a los usuarios de demo.** No estaba en la
   spec: la migración marca a los *existentes*, pero el seeder corre después
   de migrar, así que en un `composer setup` limpio las cuentas de demo
   nacerían sin verificar y no podrían entrar. Detectado por el test del
   seeder, que además tiene que fijar `app['env'] = 'local'` porque el seeder
   no siembra en `testing` (guard deliberado).
4. **Un email inexistente en `/auth/verificar` responde "código incorrecto"**
   (422), no 404: no revela quién está registrado, coherente con el reenvío.
5. **`AuthTest` y `EndurecerAuthTest`**: los usuarios de prueba que hacen login
   pasan a crearse con `email_verified_at`. Único cambio en tests existentes,
   consecuencia directa de la regla nueva.
6. **El test de "envío fallido"** simula el fallo con `Mail::shouldReceive('to')`
   lanzando, en vez de un transporte roto: es lo que el servicio captura.
7. **`login()` de `auth.js` redirige a `verificar.html` desde dentro** (no lanza
   error): la página de login no sabría distinguir el 403 de otro sin leer el
   cuerpo, y así el flujo queda en un sitio.

## Archivos tocados

- `api/database/migrations/2026_09_19_120000_verificacion_de_correo.php` — nueva
- `api/app/Models/User.php`, `api/app/Services/VerificacionDeCorreo.php`,
  `api/app/Services/ResultadoVerificacion.php`, `api/app/Mail/CodigoDeVerificacion.php`
- `api/resources/views/correo/codigo-verificacion.blade.php`, `…-texto.blade.php`
- `api/lang/{es,en}/correo.php`, `api/lang/{es,en}/mensajes.php`
- `api/app/Http/Controllers/AuthController.php`, `api/routes/api.php`,
  `api/app/Providers/AppServiceProvider.php` (limiter `verificar`)
- `api/database/seeders/UsuariosSeeder.php`, `api/.env.example`
- `api/tests/Feature/VerificacionDeCorreoTest.php` (nuevo), `AuthTest.php`, `EndurecerAuthTest.php`
- `frontend/pages/verificar.html`, `frontend/js/verificar.js` (nuevos)
- `frontend/js/auth.js`, `registro.js`, `login.js`, `pages/login.html`, `js/i18n/{es,en}.js`
- `tests-e2e/tests/verificacion-correo.spec.mjs`, `tests-e2e/global-setup.mjs` (nuevos),
  `tests-e2e/playwright.config.mjs`, `tests-e2e/README.md`, `README.md`

## Verificación

- `composer test` → **151 passed** (134 + 17).
- `tests-e2e` → **7 passed** (5 + 2), dos ejecuciones seguidas (el
  `globalSetup` deja la BD repetible). El test lee el código real del
  `laravel.log` escrito por el mailer `log`, teclea primero uno erróneo (se
  queda en la página con error) y luego el bueno → `login.html?verificado=ok`
  → login → nombre en el menú. El segundo test: cuenta sin verificar → login →
  `verificar.html?…&motivo=login`, sin token en `localStorage`.
- Criterios de backend: los 17 tests, uno por criterio (envío y hash, idioma,
  fallo de envío con warning, código correcto, incorrecto + contador, bloqueo
  al 5º, caducado, idempotente, email inexistente, formato, reenvío nuevo y
  anula el anterior, reenvío indistinguible, login 403 sin token → 200 tras
  verificar, contraseña incorrecta 401, seeder verificado, campos ocultos en
  el perfil, limiter propio).
- `migrate` sobre la BD de desarrollo: `DONE`, 3 de 3 usuarios verificados.

## Transporte en producción

Brevo configurado en Render el 2026-09-19 (variables `MAIL_*` en Environment
del Web Service de la API, remitente verificado `dorapon.soporte@gmail.com`),
tras probarlo en local con una autenticación SMTP y un registro real con
código recibido. Un tropiezo que conviene saber: Brevo rechazaba con
`525 5.7.1 Unauthorized IP address` hasta desactivar la restricción por IP en
Security → Authorised IPs (Render no garantiza IP fija de salida, así que es
lo correcto; está anotado en `.env.example`).

En local, con `MAIL_MAILER=smtp` los correos salen de verdad y el test e2e de
verificación (que lee el código de `laravel.log`) no puede pasar: para correr
la suite e2e hay que volver a `MAIL_MAILER=log`.

## Pendiente

- Nada. La recuperación de contraseña, si se hace, será otra spec y
  reutilizará el servicio y la página.
