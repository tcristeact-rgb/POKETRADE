---
estado: implementada
fecha: 2026-09-19
origen: claude-code
---

# Partir CartaController en servicios con una sola responsabilidad

## Problema

`api/app/Http/Controllers/CartaController.php` tiene 16 métodos y ~370 líneas de
código (tras el arreglo del hero) repartidas en cuatro responsabilidades que no
son de un controlador:

| Responsabilidad | Métodos hoy |
|---|---|
| HTTP del catálogo (lo que sí es suyo) | `index`, `filtros`, `show`, `buscar`, `destacadas` |
| Hidratación de cartas desde TCGdex | `crearDesdeTcgdex`, `hidratarDetalle`, `camposNeutros`, `cartaHidratada` |
| Selección del hero | `seleccionarDestacadas`, `completarDesdeTcgdex`, `setsCandidatos`, `fechaLimiteParaTenerPrecio`, `demasiadoNuevoParaTenerPrecio` |
| CRUD de administración | `store`, `update`, `destroy` |
| Utilidad sobre ids de TCGdex | `setDeCarta` |

Consecuencias concretas:

- El mapeo *payload de TCGdex → columnas de `cartas`* vive **dos veces**: en
  `camposNeutros()` (detalle de carta) y en las filas del `Carta::upsert` de
  `SetController::cachearSet()` (resumen de carta). Un cambio de campo en TCGdex
  hay que recordarlo en los dos.
- Los métodos privados no pueden recibir dependencias, así que `crearDesdeTcgdex`
  e `hidratarDetalle` hacen `app(TcgdexService::class)` a mano.
- `Idiomas::activo()` (estado global de la petición) se lee desde 6 sitios; nada
  de eso es testeable sin fijar el locale de la aplicación.
- El filtro de sets excluidos de la búsqueda global está en el controlador,
  cuando `TcgdexService` es quien conoce `setsExcluidos()` y el formato de los ids.

## Decisión

Cuatro extracciones, en este orden, cada una un **movimiento sin cambio de
comportamiento**: la suite actual (123 tests) debe pasar sin tocar ningún test
existente. Si un test hubiera que editarlo, no es un movimiento y se para.

### C) `App\Services\HidratadorDeCartas` (primero: elimina la duplicación)

Recibe `TcgdexService` por constructor. Métodos públicos, todos con el idioma
**como parámetro** (nunca `Idiomas::activo()` dentro):

- `crear(string $tcgdexId, string $idioma): ?Carta` ← `crearDesdeTcgdex`
- `hidratar(Carta $carta, string $idioma): void` ← `hidratarDetalle`
- `conDetalle(string $tcgdexId, string $idioma): ?Carta` ← `cartaHidratada`
  (el encargo lo llama `respuestaHidratada`; en el código se llama
  `cartaHidratada`)
- `filaDeResumen(array $resumen, string $idioma, string $setId): array` — la
  fila que hoy construye `SetController::cachearSet` para el upsert
  (`tcgdex_id`, `nombre_{idioma}`, `imagen_{idioma}`, `numero`, `set_id`,
  timestamps), con **los mismos nombres y valores**.

El núcleo compartido es privado: `columnasPorIdioma($datos, $idioma)` mapea
`name → nombre_{idioma}`, `image → imagen_{idioma}` (y `description` cuando
existe), y `camposNeutros()` sigue mapeando lo que no depende del idioma.
`filaDeResumen` y la hidratación de detalle usan el mismo núcleo: ese es el
mapeo único.

`SetController::cachearSet` pasa a usar `filaDeResumen`; `CartaController::show`
y el selector del hero usan `conDetalle`/`crear`/`hidratar`.

### B) `App\Services\SelectorDeDestacadas`

Recibe `TcgdexService` y `HidratadorDeCartas`. Un método público:
`seleccionar(string $idioma): array`. Dentro: `completarDesdeTcgdex`,
`setsCandidatos`, `fechaLimiteParaTenerPrecio`, `demasiadoNuevoParaTenerPrecio`
y las constantes `DESTACADAS` y `SEMANAS_HASTA_TENER_PRECIO` (el arreglo del
paso 3 viaja con él). El controlador conserva solo la caché por idioma y la
regla "el vacío no se cachea".

### D) `App\Http\Controllers\Admin\CartaController`

`store`, `update`, `destroy` tal cual, con sus `use` (`ClaveTcgValida`,
`CatalogoTcg`, `Validator`). `routes/api.php` apunta al nuevo controlador en
el grupo `es.admin`, que ya existe. Rutas, métodos y respuestas idénticos.

### E) `setDeCarta` → `TcgdexService`

`TcgdexService::setDeCarta(array $carta): string`, público. Y el filtro de
sets excluidos de la búsqueda global entra en `buscarCartas()`: se aplica
cuando la búsqueda **no** está acotada a un set (`set.id` ausente). Con
`set.id`, como hace `SetController`, el set está en nuestro índice y no puede
ser excluido; no aplicar el filtro ahí evita añadir llamadas a `setsExcluidos()`
en un camino que hoy no las hace — es lo que mantiene el movimiento sin cambio
de comportamiento.

### Inyección

`CartaController` recibe `TcgdexService`, `HidratadorDeCartas` y
`SelectorDeDestacadas` por constructor; desaparece la inyección por método en
`destacadas()` y `buscar()`, y los `app(TcgdexService::class)`. `SetController`
recibe `TcgdexService` y `HidratadorDeCartas` por constructor por la misma
razón (hoy los pasa de método en método).

## Alternativas descartadas

- **Un solo servicio `CatalogoService`** con todo: repetiría el problema con
  otro nombre.
- **Mover el mapeo al modelo `Carta`** (un `fromTcgdex()`): el modelo ya carga
  con la traducción de campos; y el mapeo depende de decisiones del servicio
  (qué idioma, qué respaldo), no de la fila.
- **Aplicar el filtro de excluidos en todas las búsquedas**, incluidas las
  acotadas a un set: añadiría peticiones a TCGdex en `SetController` y haría
  fallar tests cuyo fake no cubre `series/tcgp` (tras el paso 2, una petición
  sin fake ya no se traga). Sería un cambio de comportamiento.
- **FormRequests** para la validación repetida: fuera de alcance por encargo.

## Criterios de aceptación

- [ ] `composer test` en verde **sin editar ningún test existente** (123 tests
      antes de empezar; los mismos después, más los que se añadan).
- [ ] `grep -rn "app(TcgdexService::class)" app/` → vacío.
- [ ] `grep -n "Idiomas::activo()" app/Services/HidratadorDeCartas.php` → vacío
      (el idioma entra por parámetro).
- [ ] `grep -c "function " app/Http/Controllers/CartaController.php` ≤ 6
      (constructor + `index`, `filtros`, `destacadas`, `buscar`, `show`).
- [ ] `grep -n "setsExcluidos\|setDeCarta" app/Http/Controllers/CartaController.php` → vacío.
- [ ] `SetController::cachearSet` no construye filas a mano: usa
      `HidratadorDeCartas::filaDeResumen`.
- [ ] `php artisan route:list --path=cartas` muestra las tres rutas de admin
      apuntando a `Admin\CartaController`.
- [ ] Test unitario nuevo de `HidratadorDeCartas::filaDeResumen` que fija los
      nombres de columna del upsert (es la única prueba directa del mapeo único).

## Archivos afectados (previsión)

- `api/app/Services/HidratadorDeCartas.php` — nuevo
- `api/app/Services/SelectorDeDestacadas.php` — nuevo
- `api/app/Http/Controllers/Admin/CartaController.php` — nuevo
- `api/app/Http/Controllers/CartaController.php` — se vacía
- `api/app/Http/Controllers/SetController.php` — constructor + `filaDeResumen`
- `api/app/Services/TcgdexService.php` — `setDeCarta`, filtro en `buscarCartas`
- `api/routes/api.php` — rutas de admin
- `api/tests/Unit/HidratadorDeCartasTest.php` — nuevo

## Fuera de alcance

- FormRequests / unificar `Validator::make`.
- Cartas huérfanas de sets que no existen en `sets`.
- Cualquier cambio de comportamiento observable por HTTP.
