# PokeTrade

Aplicación de colección e intercambio de cartas del TCG de Pokémon. El catálogo se
alimenta de la API pública de TCGdex (20.386 cartas, 167 sets, 18 series).

## Stack

- **Backend**: Laravel 12, PHP 8.2, autenticación JWT (`tymon/jwt-auth`) — en `api/`
- **Frontend**: JavaScript vanilla (módulos ES), HTML5, CSS3. **Sin framework y sin paso de build** — en `frontend/`
- **Base de datos**: PostgreSQL (Supabase) en producción, SQLite en local
- **Datos de cartas**: TCGdex v2, cacheado bajo demanda con caducidad de 24h
- **Tests**: PHPUnit — 190 tests, SQLite en memoria, TCGdex mockeado con `Http::fake`
- **Despliegue**: Render (API, Docker), Vercel (frontend), Supabase (BD)

## Estructura

```
api/        backend Laravel
frontend/   HTML/CSS/JS estático
tools/      utilidades (servidor de desarrollo)
docs/       specs y decisiones (ver más abajo)
```

## Comandos

Desde `api/`:

```
composer setup     # install + .env + key:generate + jwt:secret + sqlite + migrate --seed
composer test      # 190 tests. Limpia la config cacheada primero
php artisan serve
```

Frontend:

```
node tools/servidor.mjs    # http://localhost:5500 · y /en/ para la versión inglesa
```

**Usa `composer test`, nunca `php artisan test` a secas.** Con la config cacheada,
Laravel ignora el `DB_DATABASE=:memory:` de `phpunit.xml`, la suite corre contra tu
base de datos de desarrollo y `RefreshDatabase` la vacía.

**En un entorno nuevo, `composer setup` antes que nada.** Sin `jwt:secret` fallan
11 tests con `JWTException: Secret is not set.` — no es un defecto del repo, es que
falta el paso de setup.

## Convenciones

- **El frontend no tiene paso de build.** No introduzcas bundlers, frameworks ni dependencias de npm en el frontend sin una spec que lo justifique.
- Tema claro/oscuro con CSS custom properties, sin duplicar reglas. El tema se guarda en `localStorage`.
- **i18n ES/EN en todo**, incluidos los datos de cartas (columnas por idioma). Todo texto visible pasa por `t(...)`. Nada de cadenas literales en el JS.
- Catálogo con carga cache-aside: solo se persisten los sets a los que se accede. No precargues el catálogo entero.
- `/api/cartas/buscar` es un **proxy en vivo a TCGdex**, no una consulta a la BD local. Cada llamada sale a internet: trátalo como caro.
- Las rutas literales (`/cartas/filtros`, `/cartas/buscar`, `/cartas/destacadas`) van **antes** de `/cartas/{id}` en `routes/api.php`, o se interpretan como un ID.
- En producción el frontend llama a `/api` en su propio origen y Vercel lo reescribe hacia Render. Las rutas públicas de solo lectura del catálogo llevan el middleware `cache.publica` y la CDN de Vercel las sirve desde su caché. **Nada que dependa del usuario o cambie con su actividad lleva `cache.publica`**. Si una ruta nueva devuelve el mismo contenido a cualquiera, añádelo y amplía `CachePublicaTest`.

## Flujo de trabajo: specs y decisiones

Este repo usa un contrato de archivos entre Cowork y Claude Code.

```
docs/specs/       qué hay que hacer y por qué. Lo escribe Cowork, o /nueva-spec
docs/decisions/   qué se hizo de verdad. Lo escribe Claude Code al terminar
```

Reglas:

1. Todo cambio no trivial parte de una spec en `docs/specs/`.
2. Una spec sin **criterios de aceptación verificables** no se implementa. Pídelos.
3. Al implementar, verifica tú los criterios ejecutando `composer test` o lo que
   corresponda. No preguntes al usuario si funciona.
4. Al terminar, escribe siempre `docs/decisions/<mismo-nombre>.md`, incluyendo
   **en qué te desviaste de la spec y por qué**.

Comandos disponibles: `/nueva-spec <descripción>` y `/implementa-spec <ruta>`.

## Qué no hacer

- No hagas commit ni push salvo petición explícita.
- No toques `api/.env`.
- No modifiques una spec ya escrita para que encaje con lo implementado:
  la desviación se registra en `docs/decisions/`.
