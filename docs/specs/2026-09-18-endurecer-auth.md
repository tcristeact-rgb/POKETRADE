---
estado: implementada
fecha: 2026-09-18
origen: claude-code
---

# Endurecer la autenticación: rate limiting, validación y ciclo de vida del token

## Problema

Un sweep de seguridad con scope `auth` (patrones + sondas manuales contra la
suite) encontró un hallazgo HIGH y cinco LOW. **Cada punto se ha verificado contra
el código real antes de escribir esta spec**; el resultado de esa verificación va
en cada apartado.

1. **[HIGH] Sin rate limiting.** En Laravel 12 el grupo `api` solo lleva
   `throttle:` si `bootstrap/app.php` llama a `$middleware->throttleApi()`, y no lo
   hace; tampoco hay ningún `RateLimiter::for(...)` en `AppServiceProvider`.
   *Verificado:* `grep -rn "throttle\|RateLimiter" app/ routes/ bootstrap/` vacío;
   sonda de 30 logins fallidos seguidos → 30×401 sin cabecera `X-RateLimit-*`.
   Afecta también a `/auth/registro` (alta masiva) y a `/cartas/buscar`, que es
   un proxy en vivo a TCGdex sin ningún límite propio.

2. **[LOW] `login()` devuelve 500 con payloads malformados.** Hace
   `Auth::attempt($request->only('email','password'))` sin validar.
   *Verificado:* sin clave `password` → `EloquentUserProvider` hace
   `$credentials['password']` y revienta (500); `password` como array →
   `Hash::check()` lanza `TypeError` (500).

3. **[LOW] Contraseña mínima de 6 caracteres** en registro
   (`AuthController.php:25`) y en cambio de contraseña
   (`UsuarioController.php:75`). *Verificado.* Matiz: el sweep decía que subir a 8
   "rompe el seeder"; **no es cierto** — `UsuariosSeeder` hace `Hash::make('123456')`
   directamente, sin pasar por el validador, y el login no valida longitud. Se
   actualiza igualmente por coherencia con la política.

4. **[LOW] El token JWT sobrevive al cambio de contraseña.**
   *Verificado:* sonda → token emitido antes de `PUT /usuario/password` sigue
   respondiendo 200 en `GET /usuario/perfil`. Acotado por el TTL de 60 min y la
   ausencia de refresh, pero real.

5. **[LOW] Enumeración de usuarios por tiempo en login.** `JWTGuard::attempt`
   de tymon (2.3.0) no usa `Timebox`: si el email no existe no se ejecuta bcrypt
   (~250 ms con 12 rondas). *Verificado* leyendo `vendor/tymon/jwt-auth/src/JWTGuard.php:117-126`
   (cero referencias a `Timebox`).

6. **[LOW] Validación laxa de campos.** `avatar_url` es `nullable|string`
   (acepta `javascript:alert(1)`; *verificado* por sonda: se guarda tal cual) y
   `email` no tiene `max` (*verificado:* 295 caracteres → 201 en SQLite; en
   Postgres `varchar(255)` sería un 500).

## Decisión

Implementar los seis puntos en el backend con un test por criterio. Dos de
ellos tienen una contrapartida en el frontend **fuera de `auth.js`**; por regla
del encargo se pararon antes de tocarla y se aprobaron ambas (ver "Frontend
fuera de `auth.js`").

### 1. Rate limiting

- `AppServiceProvider::boot()` define tres limiters:
  - `api`: 120/min por usuario autenticado o, si no, por IP. Es el que consume
    `throttleApi()`. Se elige el doble del valor por defecto de Laravel (60)
    porque la portada dispara tres llamadas en paralelo y una demo desde una
    misma red (NAT) comparte IP.
  - `login`: 5/min por `email normalizado + IP`. Normalizado = `strtolower(trim())`;
    si `email` no es una cadena (array, null) la clave usa `''`, para que el
    limiter nunca reviente antes que el validador.
  - `buscar`: 30/min por IP. Más holgado que login, pero acota el gasto contra
    TCGdex. Hoy el frontend solo llama a `/cartas/buscar` al enviar el buscador
    del catálogo; cuando se implemente el autocompletado
    (`2026-09-12-autocompletado-buscador.md`, aún propuesta) habrá que revisar
    el número.
- `bootstrap/app.php`: `$middleware->throttleApi()`.
- `routes/api.php`: `throttle:login` en `/auth/login` y `/auth/registro`;
  `throttle:buscar` en `/cartas/buscar`.
- **Cache store.** El `RateLimiter` usa `Cache::driver(config('cache.limiter'))`;
  `config/cache.php` no define `limiter`, así que cae al store por defecto:
  `CACHE_STORE` con default `database`. En Render no hay Redis; la tabla `cache`
  la crea `0001_01_01_000001_create_cache_table.php` y `app:arrancar` migra al
  arrancar. `DatabaseStore` implementa `add()`, que es lo que necesita
  `RateLimiter::hit()`. En tests, `phpunit.xml` fija `CACHE_STORE=array`.

### 2. Validación en `login()`

`$request->validate(['email' => 'required|string|email|max:255',
'password' => 'required|string'])` antes de `Auth::attempt`. Mismo formato de
error que el resto de la API (`{'error': primer mensaje}`, 422).

### 3. Contraseña mínima 8

`min:6` → `min:8` en registro y en `password_nuevo`. `UsuariosSeeder`: las dos
contraseñas `123456` pasan a `12345678` (`admin123` ya tiene 8). Tests que
registran con `123456` → `12345678`.

### 4. Invalidar el token al cambiar la contraseña

Backend: tras actualizar el hash, `auth()->invalidate()` (el token actual va a
la blacklist, ya activada) y `auth()->login($usuario)` para emitir uno nuevo, que
se devuelve en la respuesta como `token`. **Sin invalidación global** de otros
dispositivos (fuera de alcance por encargo).

Frontend — **decisión: devolver token nuevo y guardarlo**, no mandar al usuario
al login. Razón: el usuario acaba de demostrar que conoce la contraseña actual;
echarle es fricción gratuita, y la alternativa "que el 401 lo eche" le mostraría
"sesión expirada", que es falso. Implementación: `auth.js` exporta
`cambiarPassword(actual, nueva)` que hace la petición y, si trae `token`, llama a
`guardarSesion(token, obtenerUsuario())`. `perfil.js` sustituye su `apiFetch`
inline por esa función. **Esto toca `perfil.js`** → se paró y se aprobó. El
backend no se implementó hasta tener el OK, para no dejar el flujo a medias
(backend invalidando y frontend sin enterarse).

### 5. Igualar el coste temporal en login

En `AuthController::login`, cuando `attempt()` falla y no existe ningún usuario
con ese email, `Hash::check($password, HASH_DUMMY)` contra un bcrypt real de
coste 12 (constante en el controlador, generado una vez con
`password_hash(..., PASSWORD_BCRYPT, ['cost' => 12])`). Coste 12 es el
`BCRYPT_ROUNDS` de `.env.example`; si producción usara otro, la igualación sería
aproximada, no exacta. No se usa `Hash::make()` en caliente porque duplicaría el
coste y el servidor embebido de PHP no conserva estado entre peticiones.

### 6. Validación de `email` y `avatar_url`

- `email`: `required|string|email|max:255|unique:users,email` en registro.
- `avatar_url`: `nullable|url:http,https|max:2048` en `actualizarPerfil`. Se usa
  `url:http,https` y no `url` a secas porque la regla genérica acepta cualquier
  esquema con `://` (p. ej. `javascript://`).
- Avatares existentes: la BD local no tiene ninguno (`select avatar_url from
  users where avatar_url is not null` → 0 filas). La de producción no es
  accesible desde aquí. La regla solo se evalúa al guardar el perfil, así que un
  avatar antiguo no-http no rompe nada hasta que su dueño vuelva a guardar, y
  entonces recibe un 422 con el motivo.

## Alternativas descartadas

- **`Timebox` de Laravel en vez de hash dummy** (punto 5): garantiza un mínimo de
  200 ms pero no iguala con los ~250 ms de bcrypt-12; el encargo pide el hash.
- **Desactivar el throttle en testing**: mata el criterio del 429. Descartado por
  regla.
- **`RateLimiter::clear()` en `TestCase::setUp()`** (propuesto por el encargo):
  `Illuminate\Foundation\Testing\TestCase` reconstruye la aplicación en cada
  test (`refreshApplication()`), y con `CACHE_STORE=array` el store —y con él el
  limiter— nace vacío cada vez. Se comprueba empíricamente con la suite completa;
  si nada choca, no se añade código sin efecto. Se deja registrado en decisions.
- **Mandar al usuario al login tras cambiar la contraseña** (punto 4): ver
  Decisión.
- **`avatar_url` en el registro**: el criterio original decía "registro con
  `avatar_url=javascript:` → 422", pero `registro()` no acepta ese campo (lo
  ignora; `User::create` usa una lista explícita). Añadirlo al registro sería
  ampliar la API. El criterio se corrige a `PUT /usuario/perfil`.

## Criterios de aceptación

Cada uno con un test propio en `tests/Feature/EndurecerAuthTest.php`.

- [ ] 6 intentos de login seguidos con el mismo email → el sexto devuelve 429.
- [ ] El límite cuenta por email+IP: 5 intentos con `a@x.es` y luego 1 con `b@x.es`
      desde la misma IP → el de `b@x.es` no es 429.
- [ ] `POST /auth/login` sin campo `password` → 422, no 500.
- [ ] `POST /auth/login` con `password` como array → 422, no 500.
- [ ] `POST /auth/login` con `email` como array → 422 (el limiter no revienta).
- [ ] Registro con contraseña de 7 caracteres → 422.
- [ ] Tras cambiar la contraseña, el token anterior → 401 y la respuesta trae
      `token` nuevo que sí funciona.
- [ ] `PUT /usuario/perfil` con `avatar_url="javascript:alert(1)"` → 422.
      *(Corregido: era "registro", que no acepta el campo.)*
- [ ] Registro con email de 300 caracteres → 422.
- [ ] `GET /cartas/buscar` responde 429 a partir de la petición 31 en un minuto
      desde la misma IP.
- [ ] Login con email inexistente ejecuta `Hash::check` (verificable: el test
      mide que la respuesta tarda al menos tanto como un `Hash::check` real con
      las rondas de test; no se puede asertar igualdad exacta).
- [ ] `composer test` en verde.

## Archivos afectados (previsión)

- `api/app/Providers/AppServiceProvider.php` — tres `RateLimiter::for`
- `api/bootstrap/app.php` — `throttleApi()`
- `api/routes/api.php` — `throttle:login`, `throttle:buscar`
- `api/app/Http/Controllers/AuthController.php` — validación, min:8, max:255, hash dummy
- `api/app/Http/Controllers/UsuarioController.php` — min:8, `avatar_url`, (punto 4: invalidar + token)
- `api/database/seeders/UsuariosSeeder.php` — contraseñas de 8
- `api/tests/Feature/AuthTest.php` — `123456` → `12345678`
- `api/tests/Feature/EndurecerAuthTest.php` — nuevo
- `frontend/js/auth.js` — `cambiarPassword()` (punto 4, pendiente de aprobación)
- `frontend/js/perfil.js` — usar `cambiarPassword()` (punto 4, **fuera de auth.js**)

## Frontend fuera de `auth.js` (aprobado)

1. **Punto 4**: `perfil.js` sustituye su `apiFetch` inline por
   `cambiarPassword()` de `auth.js` (importada como `cambiarPasswordApi` para no
   chocar con la función local del mismo nombre).
2. **Punto 3**: el cliente validaba "mínimo 6": `registro.js:51`, `perfil.js:104`,
   `pages/registro.html:83` (`minlength="6"`), `pages/perfil.html:99`
   (placeholder), y las claves `auth.ayudaPassword`, `auth.min6`,
   `perfil.minimo6`, `perfil.passwordMin6` en `i18n/es.js` y `i18n/en.js`. Las
   claves que llevan el número en el nombre se renombran (`min6` → `min8`,
   `minimo6` → `minimo8`, `passwordMin6` → `passwordMin8`); `auth.ayudaPassword`
   solo cambia el texto.

## Fuera de alcance

- Content-Security-Policy en `vercel.json` (scope config, spec aparte).
- Verificación de email y recuperación de contraseña.
- Mover el JWT fuera de `localStorage`.
- Invalidación global de tokens (todos los dispositivos).
- Comprobación de contraseñas filtradas (`Password::uncompromised()`): sale a
  internet; no lo pide el encargo.
