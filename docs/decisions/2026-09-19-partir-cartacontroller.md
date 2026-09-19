---
spec: docs/specs/2026-09-19-partir-cartacontroller.md
fecha: 2026-09-19
resultado: completo
---

# Partir CartaController en servicios con una sola responsabilidad

Este registro cubre los cuatro pasos del encargo "arregla los hallazgos del
análisis del backend": los tres arreglos previos (sin spec, triviales) y la
partición (con spec).

## Qué se implementó

### Paso 1 — `composer setup` moría a los 300 s

`"composer install"` → `"@composer install"` en el script `setup`. Sin
`config.process-timeout` (una cosa o la otra).

### Paso 2 — `TcgdexService::get()` invisible en los logs

- `catch (\Throwable)` → `catch (ConnectionException $e)` con `Log::warning`
  (ruta, idioma, clase, mensaje).
- Los 5xx (y cualquier no-404) dejan `Log::warning` con `status`.
- Un 200 cuyo cuerpo no parsea a array deja `Log::warning` y devuelve null
  (antes: `TypeError` fuera del try → 500).
- `TcgdexLogTest` (5 tests, `Log::shouldReceive`).

### Paso 3 — la portada sin hero en un clon limpio

- `SEMANAS_HASTA_TENER_PRECIO = 8`: `setsCandidatos()` descarta los sets con
  menos de 8 semanas y los sin fecha; en el camino en vivo (sin índice) el
  filtro lo aplica `completarDesdeTcgdex` con el `releaseDate` de cada set.
- Relleno con cartas sin precio **que tengan imagen** (`Carta::conImagen()`).
- README actualizado (líneas 79 y 198).
- `DestacadasSetsNuevosTest` (5 tests).

### Paso 4 — partición (la spec)

| Extracción | Resultado |
|---|---|
| C) `App\Services\HidratadorDeCartas` | `crear`, `hidratar`, `conDetalle`, `filaDeResumen`; núcleo privado `columnasPorIdioma` + `camposNeutros`. `SetController::cachearSet` usa `filaDeResumen`. |
| B) `App\Services\SelectorDeDestacadas` | `seleccionar(string $idioma)`; se lleva las constantes y el arreglo del paso 3. |
| D) `App\Http\Controllers\Admin\CartaController` | `store`, `update`, `destroy`; rutas actualizadas. |
| E) `TcgdexService::setDeCarta` | público; el filtro de excluidos vive en `buscarCartas()` cuando no hay `set.id`. |

`CartaController`: 611 → 255 líneas (121 de código), 16 → 6 funciones
(constructor + 5 acciones). Inyección por constructor en `CartaController` y
`SetController`; cero `app(TcgdexService::class)`.

## Desviaciones respecto a la spec

1. **Paso 2 rompió 5 tests existentes** (`CartaTest` ×1, `DestacadasTest` ×4) y
   se paró. Causa: `TestCase` hace `Http::preventStrayRequests()` y esos tests,
   sin `Http::fake`, confiaban en que la `StrayRequestException` (una
   `RuntimeException`) fuera tragada por el `catch (\Throwable)` para simular
   "TCGdex caído" — su propio comentario lo decía. Con aprobación del usuario
   ("haz el 1"): helper `conTcgdexCaido()` en `TestCase` (todo 503) y una línea
   en cada uno de los 5 tests. **No** se puso un fake global en `setUp()`: los
   stubs de `Http::fake` se resuelven con `->filter()->first()` (gana el primero
   registrado) y taparía los fakes propios de cada test. El comentario de
   `TestCase` se reescribió porque era falso tras el cambio, y el de
   `CartaTest::test_navegacion_del_detalle_se_limita_al_set` también:
   afirmaba que `detalle_synced_at` evitaba la hidratación, pero
   `detalladoEn()` mira `idiomas_detallados`.
2. **`RequestException` no se captura** (el encargo decía "si hace falta"): con
   `throw: false` nunca sale de `send()` — sondeado: 404/429 vuelven como
   respuesta (1 intento), 5xx como respuesta tras 3 intentos. Solo
   `ConnectionException` llega al `catch`.
3. **Cambio de comportamiento confirmado (2b)**: una excepción que no sea de
   conexión dentro del cliente HTTP ya no se convierte en 503 `tcgdex_caido`:
   es un 500. Hay test que lo fija
   (`test_una_excepcion_que_no_es_de_conexion_ya_no_se_disfraza_de_tcgdex_caido`).
4. **El relleno sin precio exige imagen**. No estaba en el encargo. Sin esa
   condición, `DestacadasTest::test_devuelve_las_que_haya_si_no_llega_a_4_con_precio`
   (10 cartas sin precio ni imagen + 1 con precio → exactamente 1) habría
   pasado a devolver 4 y habría roto el criterio "DestacadasTest sin tocar".
   Pero no es un apaño para el test: el hero descarta en cliente las cartas sin
   imagen (`inicio.js:74`), así que devolverlas sería prometer cuatro y pintar
   menos. Lo verificado del frontend: `precio(null)` devuelve `null` y el hero
   omite la etiqueta; no se tocó nada de `frontend/`.
5. **Umbral en dos sitios, no en uno**: `setsCandidatos()` filtra por
   `fecha_lanzamiento` (camino con índice, sin coste) y `completarDesdeTcgdex`
   por `releaseDate` (camino en vivo, donde no hay índice). Un payload sin
   `releaseDate` no descarta nada — así el TCGdex de mentira de
   `DestacadasTest`, que no manda fecha, sigue igual.
6. **Tests nuevos en archivos nuevos** (`DestacadasSetsNuevosTest`,
   `TcgdexLogTest`, `HidratadorDeCartasTest`), no dentro de `DestacadasTest`,
   para respetar literalmente "sigue pasando sin tocarlo" más allá de las 4
   líneas aprobadas del punto 1.
7. **`respuestaHidratada` no existía**: el método se llama `cartaHidratada`;
   es lo que se movió como `conDetalle`.
8. **`filaDeResumen` hace `unset` de la descripción** que `columnasPorIdioma`
   produce: para que la fila del upsert tenga exactamente las mismas claves
   que antes (`tcgdex_id, nombre_x, imagen_x, numero, set_id, created_at,
   updated_at`) y el movimiento sea idéntico byte a byte en la BD. El test
   unitario lo fija.
9. **El filtro de excluidos en `buscarCartas()` solo sin `set.id`**: aplicarlo
   también en la búsqueda acotada de `SetController` añadiría llamadas a
   `setsExcluidos()` en un camino que hoy no las hace y, tras el paso 2,
   cualquier petición sin fake revienta el test.
10. **Huérfanas**: 0 de 367 en la BD local tras el paso 3 (las 13 que había
    vivían en el clon temporal de la auditoría, ya borrado). Producción no es
    accesible desde aquí. No se tocó nada.

## Archivos tocados

- `api/composer.json` — `@composer install`
- `api/app/Services/TcgdexService.php` — `get()` con log y catch estrecho; `setDeCarta`; filtro en `buscarCartas`
- `api/app/Http/Controllers/CartaController.php` — vaciado: 5 acciones + constructor
- `api/app/Http/Controllers/SetController.php` — constructor; `filaDeResumen`
- `api/app/Http/Controllers/Admin/CartaController.php` — nuevo (CRUD)
- `api/app/Services/HidratadorDeCartas.php` — nuevo
- `api/app/Services/SelectorDeDestacadas.php` — nuevo (con el arreglo del hero)
- `api/app/Models/Carta.php` — `scopeConImagen`
- `api/routes/api.php` — rutas admin → `Admin\CartaController`
- `api/tests/TestCase.php` — `conTcgdexCaido()`, comentario
- `api/tests/Feature/CartaTest.php`, `DestacadasTest.php` — 5 líneas `conTcgdexCaido()` (aprobado)
- `api/tests/Feature/TcgdexLogTest.php`, `DestacadasSetsNuevosTest.php`, `api/tests/Unit/HidratadorDeCartasTest.php` — nuevos
- `README.md` — dos frases sobre el hero

## Verificación

`composer test` tras cada paso: 113 → **113** (paso 1) → **118** (paso 2, tras
el punto 1 aprobado) → **123** (paso 3) → **123** (paso 4 C, B, D, E: los
mismos tests, sin editar ninguno) → **125** con el unitario nuevo.

- [x] `grep -rn "app(TcgdexService::class)" app/` → vacío
- [x] `grep -n "Idiomas::activo()" app/Services/HidratadorDeCartas.php` → vacío (y en `SelectorDeDestacadas`)
- [x] `grep -c "function " CartaController.php` → 6
- [x] `setsExcluidos|setDeCarta` fuera del controlador
- [x] `SetController::cachearSet` → `filaDeResumen`
- [x] `route:list --path=cartas`: POST/PUT/DELETE → `Admin\CartaController`
- [x] `tests/Unit/HidratadorDeCartasTest` fija las columnas del upsert
- [x] Paso 3: `DestacadasSetsNuevosTest` — 3 sets nuevos sin precio → 4 cartas
      del maduro; sin índice se saltan por `releaseDate` sin hidratar nada;
      sin `releaseDate` no se descarta; sin precio en ningún set → 4 con
      imagen y precio null; sin imagen → `[]`
- [x] Paso 2: `TcgdexLogTest` — warning con ruta/idioma/excepción/mensaje;
      warning con status en 5xx; 200 sin JSON → null + warning; 404 → `[]` sin
      warning; excepción ajena → propaga

## Pendiente

- El README sigue diciendo "96 tests" en tres sitios (ya estaba desactualizado
  antes: hay 125). Fuera del alcance de este encargo.
- `DestacadasTest::test_con_la_bd_vacia_se_completa_desde_tcgdex` y
  compañía siguen probando el camino en vivo con un TCGdex sin `releaseDate`;
  el caso con fecha lo cubre `DestacadasSetsNuevosTest`.
