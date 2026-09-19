---
spec: docs/specs/2026-09-12-cache-aside-puro.md
fecha: 2026-09-12
resultado: completo
---

# Cache-aside puro: eliminar el sembrado de catálogo y datos de prueba

## Qué se implementó

- Eliminados `CartasSeeder`, `InventarioSeeder` y `TradeosSeeder`. `DatabaseSeeder`
  solo llama a `UsuariosSeeder`, que se conserva tal cual.
- `/api/cartas/destacadas` se completa en vivo desde TCGdex cuando la BD no da
  4 cartas con precio:
  - Candidatos: los 3 sets más recientes del índice (`sets` por
    `fecha_lanzamiento`). Si el índice está vacío (BD recién creada), se pide a
    TCGdex la lista de series, se descartan las de `config('tcgdex.series_excluidas')`,
    y se toman los sets de la última serie (2 peticiones, cacheadas 24 h).
  - Dentro de cada set se recorren las cartas **desde el final** (secretas e
    ilustraciones especiales: las caras) y se hidratan solo las necesarias, con
    un tope de `faltan × 3` intentos.
  - Las cartas de respaldo se **persisten** por el mismo camino que `show()`
    (`crearDesdeTcgdex` / `hidratarDetalle`, ahora compartidos vía
    `cartaHidratada()`), así tienen `id` interno y la siguiente selección ya
    las encuentra en la BD.
  - Si TCGdex devuelve `null` en cualquier paso, se corta y se devuelve lo que
    haya. Sin excepciones: siempre 200.
  - `Cache::remember` → `Cache::get` / `Cache::put`: un resultado vacío no se
    guarda nunca. Mismo TTL (1 h) y misma clave por idioma.
- `Http::preventStrayRequests()` en `tests/TestCase.php`: ningún test de la
  suite puede salir a internet.
- Cuatro tests nuevos en `DestacadasTest`.
- README: puesta en marcha sin sembrado de cartas, y párrafo en arquitectura
  sobre el respaldo en vivo del hero. Contador de tests 92 → 96 (también en
  `CLAUDE.md`).

## Desviaciones respecto a la spec

1. **Las cartas de respaldo se persisten, y se devuelven con la forma de
   `Carta::toArray()`, no con la de `buscar()`.** La nota de implementación
   sugería montar `imagen_low` / `imagen_high` a mano como hace `buscar()`,
   que devuelve `id: null` para las cartas que no están en la BD. Pero el hero
   (`frontend/js/inicio.js`) filtra `c.id && (...)` y enlaza a
   `detalle-carta.html?id=${carta.id}`: una carta sin `id` interno se descarta
   y el hero se quedaría igual de vacío. Persistirlas (exactamente lo que ya
   hace `show()` al abrir una carta desde la búsqueda global) resuelve eso, es
   cache-aside y no un sembrado (solo entran las cuatro que se enseñan), y
   evita volver a TCGdex en la siguiente selección. Los campos que consume el
   hero son los mismos que hoy.

2. **`Http::preventStrayRequests()` en el `TestCase` base.** No estaba en la
   spec. Hacía falta para cumplir a la vez dos criterios: que los tests
   existentes de `DestacadasTest` pasen *sin modificarlos* y que *ningún test
   salga a la red*. Tres de esos tests dejan la BD con menos de 4 cartas con
   precio y no tienen `Http::fake`: con el respaldo nuevo saldrían a TCGdex de
   verdad. Con `preventStrayRequests`, la petición revienta,
   `TcgdexService::get()` la captura y devuelve `null`, y el controlador la
   trata como "TCGdex no contestó": el resultado es el que esos tests esperan.
   El efecto colateral es una protección para toda la suite. Coste: ~0,6 s por
   petición bloqueada (los reintentos de `Http::retry` duermen 300 ms), unos
   3 s en total.

3. **Con el índice vacío se consulta a TCGdex la lista de series, no la de
   sets.** `/v2/{lang}/sets` no trae `releaseDate` ni serie, así que no hay
   forma barata de saber cuál es reciente ni de excluir Pocket. La lista de
   series sí permite aplicar `series_excluidas`, y su último elemento es la
   serie más reciente. Es el camino de una BD sin `tcgdex:sync-sets`; en
   producción el índice siempre existe.

4. **Las cartas traídas en vivo se ordenan por precio descendente entre
   ellas.** No lo pedía la spec; es coherente con lo que hace la parte de la
   BD y no cambia ningún criterio.

5. **Contador de tests en README y `CLAUDE.md`.** Fuera de la lista de
   archivos de la spec, pero dejar "92 tests" con 96 en la suite sería mentir.

## Archivos tocados

- `api/database/seeders/CartasSeeder.php` — eliminado
- `api/database/seeders/InventarioSeeder.php` — eliminado
- `api/database/seeders/TradeosSeeder.php` — eliminado
- `api/database/seeders/DatabaseSeeder.php` — solo `UsuariosSeeder`; comentario sin `--class=CartasSeeder`
- `api/database/seeders/UsuariosSeeder.php` — comentario: en producción no se siembra nada
- `api/database/migrations/2026_03_22_235631_crear_tabla_cartas.php` — comentario que nombraba `CartasSeeder`
- `api/app/Console/Commands/SincronizarCartasTcgdex.php` — aviso: cómo se puebla ahora
- `api/app/Http/Controllers/CartaController.php` — `destacadas()` con `get`/`put`; nuevos `seleccionarDestacadas()`, `completarDesdeTcgdex()`, `setsCandidatos()`, `cartaHidratada()`; constante `DESTACADAS`
- `api/tests/TestCase.php` — `Http::preventStrayRequests()`
- `api/tests/Feature/DestacadasTest.php` — 4 tests nuevos y dos helpers (`respuestaTcgdex`, `crearIndice`); los 6 existentes intactos
- `README.md` — puesta en marcha, arquitectura, contador de tests
- `CLAUDE.md` — contador de tests
- `docs/specs/2026-09-12-cache-aside-puro.md` — solo `estado:`

## Verificación

**Eliminación limpia**

- [x] Los tres seeders ya no existen — `Get-ChildItem api\database\seeders` → solo `DatabaseSeeder.php` y `UsuariosSeeder.php`
- [x] `UsuariosSeeder` sigue y `DatabaseSeeder` solo lo llama a él — inspección del archivo; la salida de `--seed` muestra solo `UsuariosSeeder ... DONE`
- [x] `git grep -n -i -E "CartasSeeder|InventarioSeeder|TradeosSeeder" -- api README.md` → sin coincidencias (exit 1)
- [x] `php artisan migrate:fresh --seed --force` sobre un SQLite vacío en el scratchpad (`DB_DATABASE` por variable de entorno, sin tocar `api/.env`) → exit 0, `users=3 cartas=0`, sin errores de clave foránea

**Destacadas con respaldo en vivo**

- [x] Con cartas suficientes, igual que hoy — los 6 tests existentes de `DestacadasTest` pasan sin modificar
- [x] BD vacía → 4 cartas de TCGdex con los campos del hero — `test_con_la_bd_vacia_se_completa_desde_tcgdex` (id entero, nombre, imagen_low/high, precio, persistidas). Además, comprobación manual contra el TCGdex real con la BD del scratchpad vacía: devolvió 4 cartas de `me05` (Mega-Darkrai ex 155,55 €, Morpeko ex 65,41 €…), `cartas en BD=4`, caché poblada
- [x] Relleno parcial sin repetir — `test_relleno_parcial_sin_duplicados` (2 de la BD + 2 de TCGdex, `tcgdex_id` únicos, la que ya estaba no se vuelve a pedir)
- [x] TCGdex caído → `{"data": []}` con 200 — `test_si_tcgdex_no_responde_devuelve_data_vacio_con_200` (`assertExactJson`)
- [x] Vacío no se cachea — `test_un_resultado_vacio_no_se_cachea` (tras el fallo `Cache::has` es false; la segunda petición devuelve 4)
- [x] Caché de 1 h e independiente por idioma — `test_la_respuesta_se_cachea_una_hora` y `test_la_cache_de_destacadas_es_independiente_por_idioma`, sin cambios; el código conserva `3600` y la clave `cartas.destacadas.{idioma}`

**Tests**

- [x] Tests nuevos que cubren los cuatro escenarios — los cuatro de arriba, en `DestacadasTest`
- [x] `Http::fake` y ningún test sale a la red — los nuevos usan `Http::fake`; `Http::preventStrayRequests()` en `TestCase` lo garantiza para toda la suite
- [x] `cd api && composer test` pasa entero — `Tests: 96 passed (454 assertions)`

**Documentación**

- [x] README refleja cache-aside como único mecanismo y `tcgdex:sync-sets` como lo que deja series y sets navegables — sección "Running it locally" y párrafo nuevo en "A 20,386-card catalog you cannot download"
- [x] Puesta en marcha sin sembrar cartas — `migrate --seed  # schema + demo users (no cards)`

## Pendiente

- Nada de la spec. Dos notas para quien siga:
  - `crearDesdeTcgdex()` devuelve `null` tanto si TCGdex no contestó como si
    la carta no existe en ningún catálogo. El respaldo trata ambos como "caído"
    y corta. Una carta listada en el resumen del set pero ausente en el
    detalle es rarísima; si se diera, el hero de esa petición saldría corto y,
    al no ser vacío, quedaría cacheado una hora.
  - El script `composer setup` sigue sin ejecutar `tcgdex:sync-sets` (era así
    antes). El README ya lo dice.
