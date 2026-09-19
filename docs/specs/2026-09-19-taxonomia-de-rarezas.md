---
estado: implementada
fecha: 2026-09-19
origen: propietario + claude-code
---

# Taxonomía cerrada de rarezas, con símbolo

## Problema

El filtro de rareza del catálogo ofrece las 40 rarezas que TCGdex conoce,
ordenadas alfabéticamente, existan o no en nuestro catálogo, y con nombres
que mezclan eras ("Holo Rara (clásica)", "Rara Variocolor VMAX"…). Para
quien colecciona es inservible: quiere las categorías del TCG real, en orden
de rareza, con su símbolo, y un cajón para todo lo raro.

## Datos reales (paso 0, BD local, 552 cartas)

La columna cruda `rareza` **ya no existe**: la migración
`2026_07_13_000000_normalizar_tipo_y_rareza_a_claves` la volcó en
`rareza_key` (40 claves de `App\Support\CatalogoTcg`) y la borró. Esa clave
es una normalización sin pérdida: cada una vuelve al texto exacto de TCGdex.
Es, en la práctica, el valor crudo que hay que conservar.

| `rareza_key` | cartas | | `rareza_key` | cartas |
|---|---:|---|---|---:|
| *NULL* (sin hidratar) | 181 | | double-rare | 12 |
| common | 108 | | holo-rare-vstar | 8 |
| uncommon | 91 | | special-illustration-rare | 7 |
| rare | 48 | | holo-rare-vmax | 5 |
| ultra-rare | 30 | | hyper-rare | 3 |
| holo-rare | 21 | | radiant-rare | 3 |
| holo-rare-v | 17 | | promo | 1 |
| illustration-rare | 16 | | secret-rare | 1 |

Las 181 nulas son exactamente las 181 con `detalle_synced_at = NULL`: entraron
por el listado de un set (que no trae rareza) y nadie las ha abierto. No es
un fallo del mapeo.

TCGdex sirve hoy 42 rarezas (`GET /v2/en/rarities`); dos son nuevas desde
julio y no están en `CatalogoTcg`: `Futuristic Rare` y `Pikachu Rare`.

## Decisión

### Taxonomía (orden canónico, de menor a mayor; excepciones al final)

| clave | ES | EN | símbolo |
|---|---|---|---|
| comun | Común | Common | 1 círculo negro |
| infrecuente | Infrecuente | Uncommon | 1 rombo negro |
| rara | Rara | Rare | 1 estrella negra |
| doble_rara | Doble Rara | Double Rare | 2 estrellas negras |
| ace_spec | AS Táctico | Ace Spec | 1 estrella rosa |
| ilustracion | Rara Ilustración | Illustration Rare | 1 estrella dorada |
| ultra_rara | Ultra Rara | Ultra Rare | 2 estrellas plateadas |
| ilustracion_especial | Rara Ilustración Especial | Special Illustration Rare | 2 estrellas doradas |
| hiper_rara | Hiper Rara | Hyper Rare | 3 estrellas doradas |
| excepciones | Excepciones | Other | sin símbolo |

### Mapeo `rareza_key` (TCGdex) → categoría

Criterio: **el símbolo impreso en la carta**. Lo que no lleva uno de los
nueve símbolos va a `excepciones`.

| categoría | claves de TCGdex |
|---|---|
| comun | common |
| infrecuente | uncommon |
| rara | rare, holo-rare, rare-holo, rare-holo-lvx, rare-prime, legend, holo-rare-v, holo-rare-vmax, holo-rare-vstar |
| doble_rara | double-rare |
| ace_spec | ace-spec-rare |
| ilustracion | illustration-rare, shiny-rare |
| ultra_rara | ultra-rare, full-art-trainer, shiny-rare-v, shiny-rare-vmax |
| ilustracion_especial | special-illustration-rare, shiny-ultra-rare |
| hiper_rara | hyper-rare, mega-hyper-rare, secret-rare, black-white-rare |
| excepciones | none, promo, classic-collection, radiant-rare, amazing-rare, las 10 de Pocket (one-diamond … crown) **y cualquier clave desconocida** |

Sobre las 371 cartas locales con rareza: excepciones = 4 (1,1 %). Muy por
debajo del 15 % que obligaría a repensar.

### Paso 1 — una sola fuente de verdad: `App\Support\Rarezas`

- `ORDEN`: las 10 claves en orden canónico.
- `SIMBOLOS`: por clave, `['forma' => 'circulo'|'rombo'|'estrella', 'color' => 'negro'|'rosa'|'dorado'|'plateado', 'cantidad' => 1..3]`, `null` para `excepciones`.
- `MAPA`: `rareza_key` de TCGdex → categoría (la tabla de arriba).
- `categoria(?string $rarezaKey): ?string` — `null` → `null` (sin hidratar);
  **cualquier clave que no esté en `MAPA` → `excepciones`**.
- `clavesTcgdex(string $categoria): array` — las `rareza_key` que caen en
  ella (para el `WHERE IN` y para la búsqueda en vivo).
- `claveDesdeTcgdex(?string $textoCrudo): ?string` — lo que usa el
  hidratador: `CatalogoTcg::claveRareza()` y, si TCGdex manda un valor que
  no conocemos, su *slug* (`'Futuristic Rare'` → `'futuristic-rare'`). Así
  el valor se conserva (remapeable) y `categoria()` lo deja en `excepciones`.
- `normalizar(?string $valor): ?string` — acepta una categoría, una
  `rareza_key` antigua o un texto de TCGdex (enlaces viejos `?rareza=holo-rare`
  siguen funcionando) y devuelve la categoría o `null`.
- `filtros(iterable $clavesPresentes): array` — `[{clave, etiqueta, simbolo}]`
  en orden canónico, solo las categorías con alguna clave presente.
- Los nombres ES/EN viven en `lang/{es,en}/tcg.php` bajo `categorias`, que es
  donde el proyecto tiene todo texto visible; la clase los sirve con
  `nombre()`. Las 40 entradas `rarezas` de `lang` y `CatalogoTcg::rarezas()`
  desaparecen: ya no hay quien las lea.

### Paso 2 — datos

- **Sin migración.** `rareza_key` no cambia: es el valor crudo. La categoría
  se deriva en código, así que remapear es editar `MAPA` y no tocar la BD.
- `HidratadorDeCartas::camposNeutros()` usa `Rarezas::claveDesdeTcgdex()`.
- El índice `cartas_rareza_key_index` sirve al filtro nuevo
  (`WHERE rareza_key IN (…≤ 10 valores)`).

### Paso 3 — API

- `Carta` añade a `$appends`: `rareza_categoria` (clave) y `rareza_simbolo`
  (el objeto de `SIMBOLOS`, o `null`). `rareza` pasa a ser **el nombre de la
  categoría** en el idioma activo (antes: el nombre fino de TCGdex).
- `GET /cartas/filtros` → `rarezas` = `Rarezas::filtros(DISTINCT rareza_key
  del catálogo)`: orden canónico, solo las presentes, cada una
  `{clave, etiqueta, simbolo}`.
- `GET /cartas?rareza=<categoría>` → `whereIn('rareza_key',
  Rarezas::clavesTcgdex(...))`. Un valor que no normaliza a nada devuelve 0
  cartas (hoy `where('rareza_key', null)` devolvía las no hidratadas).
- `GET /cartas/buscar?rareza=<categoría>` → a TCGdex se le manda
  `rarity=eq:A|B|C` con los textos de esa categoría en el catálogo del idioma
  (comprobado contra la API: `|` es OR y `eq:` es igualdad exacta). Si ninguna
  de sus claves existe en ese idioma, no se hace la petición. De paso se
  corrige que hoy `rarity=Rare` iba en modo laxo (subcadena) y en `sv03`
  devolvía 64 cartas en vez de 10.
- `POST/PUT /cartas` (admin) sigue validando contra `CatalogoTcg`: el admin
  no es TCGdex y una rareza inventada a mano se sigue rechazando.

### Paso 4 — frontend

- `utils.js` exporta `simboloRareza(simbolo)`: `''` sin símbolo; si no,
  `<span class="rareza-simbolo rareza-simbolo--{color}" aria-hidden="true">{glifo × cantidad}</span>`
  con glifos `●` `◆` `★`. Es la única función que dibuja símbolos.
- **Filtro del catálogo**: opciones en el orden que llega de la API, texto
  `"{glifos} {nombre}"`. Un `<option>` no admite HTML ni color fiable, así que
  el color se pierde en el desplegable; el nombre al lado es lo que carga el
  significado (regla de accesibilidad de esta spec), y no se monta un listbox
  a medida por esto.
- **Detalle de carta**: la fila "Rareza" sale de la lista de atributos y pasa
  a un bloque `.detalle-rareza` **abajo a la izquierda** de la información,
  como en una carta real: símbolo (aria-hidden) + nombre, siempre juntos.
- Colores por tokens en `:root` y `[data-tema="oscuro"]`: `--simbolo-negro`,
  `--simbolo-rosa`, `--simbolo-dorado`, `--simbolo-plateado`. "Negro" en
  oscuro es claro: el nombre del token es semántico del TCG, no del pixel.
- i18n: `catalogo.todasRarezas` se mantiene; los nombres de categoría vienen
  de la API ya traducidos.

## Alternativas descartadas

- **Columna `rareza_categoria` + migración de recálculo.** Duplica en la BD un
  dato derivable con una tabla de 42 entradas, y obliga a una migración cada
  vez que se ajuste el mapeo. El `IN` sobre el índice existente es igual de
  rápido para ≤ 10 valores.
- **Recalcular `rareza_key` a las 10 categorías.** Pierde el nivel fino, que
  es el valor crudo que se quiere conservar, y rompe la búsqueda en vivo (que
  necesita el texto exacto de TCGdex).
- **NULL → `excepciones`.** Una Común sin hidratar saldría como "Excepciones".
- **Símbolos como imágenes/SVG.** El frontend no tiene build ni assets nuevos.

## Criterios de aceptación

**Backend** (`tests/Feature/RarezasTest.php`, nuevo)

- [ ] Toda `rareza_key` de la muestra del paso 0 (las 15 claves reales) cae en
      la categoría de la tabla; `categoria(null)` es `null`.
- [ ] Una rareza inventada (`'Rareza Inventada 2027'`) hidratada desde TCGdex
      se guarda, la carta responde `rareza_categoria: 'excepciones'` y
      `rareza: 'Excepciones'`/`'Other'`, y `/cartas?rareza=excepciones` la
      devuelve. Nada revienta.
- [ ] `/cartas/filtros` devuelve las rarezas en orden canónico, solo las
      presentes en el catálogo, cada una con `clave`, `etiqueta` y `simbolo`.
- [ ] `/cartas?rareza=excepciones` devuelve las promos (y `radiant-rare`) y
      ninguna otra; `/cartas?rareza=rara` incluye `holo-rare-v`.
- [ ] `/cartas/buscar?rareza=ultra_rara` manda a TCGdex `rarity=eq:Ultra
      Rare|Full Art Trainer|…` y no consulta el catálogo español para una
      categoría sin texto en español.
- [ ] Toda categoría tiene nombre en ES y EN.
- [ ] `composer test` en verde.

**Frontend** (`tests-e2e/tests/rarezas.spec.mjs`, nuevo)

- [ ] El desplegable `#filtro-rareza` tiene las opciones en orden canónico y
      cada texto empieza por su glifo (o ninguno para Excepciones).
- [ ] El detalle de una carta con rareza muestra `.detalle-rareza` con un
      `.rareza-simbolo[aria-hidden="true"]` seguido del nombre, en ES y en EN.
- [ ] La suite e2e existente sigue en verde (salvo verificación de correo,
      que en local no puede pasar con `MAIL_MAILER=smtp`).

## Archivos afectados (previsión)

- `api/app/Support/Rarezas.php` — nuevo
- `api/app/Support/CatalogoTcg.php` — fuera `rarezas()` y el orden por etiqueta de rarezas
- `api/app/Models/Carta.php`, `api/app/Services/HidratadorDeCartas.php`,
  `api/app/Services/TcgdexService.php`, `api/app/Http/Controllers/CartaController.php`
- `api/lang/{es,en}/tcg.php`
- `api/tests/Feature/RarezasTest.php` (nuevo), `BusquedaGlobalTest.php`,
  `CatalogoTcgTest.php`, `CartaTest.php` (ajustes de nombres)
- `frontend/js/utils.js`, `filtros-catalogo.js`, `detalle-carta.js`,
  `frontend/css/estilos.css`
- `tests-e2e/tests/rarezas.spec.mjs` — nuevo

## Fuera de alcance

- Símbolo en las tarjetas de los grids y en el inventario (solo nombre).
- Ordenar el listado de cartas por rareza.
- Hidratar las 181 cartas sin detalle.
