---
spec: docs/specs/2026-09-19-taxonomia-de-rarezas.md
fecha: 2026-09-19
resultado: completo
---

# Taxonomía cerrada de rarezas, con símbolo

## Qué se implementó

- **`App\Support\Rarezas`** (nuevo): única fuente de verdad de las diez
  categorías — `ORDEN`, `SIMBOLOS` (`{forma, color, cantidad}` o `null`),
  `MAPA` (clave fina de TCGdex → categoría), `categoria()`,
  `clavesTcgdex()`, `clavesClasificadas()`, `claveDesdeTcgdex()`,
  `normalizar()`, `nombre()`, `filtros()`. Regla dura: lo que no está en
  `MAPA` es `excepciones`; `null` (sin hidratar) sigue siendo `null`.
- **Modelo `Carta`**: `rareza` pasa a ser el nombre de la categoría; nuevos
  appends `rareza_categoria` y `rareza_simbolo`; scope `deRareza()` —
  `WHERE rareza_key IN (…)` por el índice existente y, para `excepciones`,
  el complemento (`NOT NULL AND NOT IN (claves clasificadas)`), que es lo que
  atrapa las claves que TCGdex invente.
- **Hidratador**: `Rarezas::claveDesdeTcgdex()` — lo conocido se normaliza
  como antes; lo desconocido se guarda como slug (`'futuristic-rare'`), no se
  pierde, y cae en excepciones.
- **API**: `/cartas/filtros` devuelve las categorías presentes en el catálogo,
  en orden canónico, con `{clave, etiqueta, simbolo}`; `/cartas?rareza=` y el
  filtro de `/sets/{id}/cartas` aceptan la categoría (y, por compatibilidad,
  una clave fina o un texto de TCGdex); la búsqueda en vivo manda a TCGdex
  `rarity=eq:A|B|C` con los textos de la categoría en cada idioma.
- **`lang/{es,en}/tcg.php`**: las 40 entradas `rarezas` se sustituyen por las
  10 `categorias`. `CatalogoTcg::rarezas()` desaparece (sin llamadores).
- **Frontend**: `utils.js` exporta `glifosRareza()` y `simboloRareza()` (el
  único sitio que dibuja símbolos: `●` `◆` `★` × cantidad, clase de color,
  `aria-hidden="true"`); el desplegable del catálogo lista "{glifos} {nombre}"
  en el orden que llega; el detalle saca la rareza de la lista de atributos y
  la pone en `.detalle-rareza`, al final de la columna de información (abajo a
  la izquierda), símbolo + nombre dentro del mismo badge.
- **CSS**: tokens `--simbolo-negro/rosa/dorado/plateado` en `:root` y en
  `[data-tema="oscuro"]` (el "negro" es claro en oscuro: el nombre es el del
  TCG, no el del pixel); `.badge-rareza` pasa a fondo neutro para que el color
  del símbolo se lea; `.rareza-simbolo--*`, `.detalle-rareza`.
- **Tests**: `RarezasTest` (7) y `tests-e2e/tests/rarezas.spec.mjs` (2).

## Desviaciones respecto a la petición original

1. **Ni migración ni recálculo de `rareza_key`; ni columna `rareza` cruda.**
   La columna cruda ya no existía (la borró `normalizar_tipo_y_rareza` en
   julio, avisado antes de tocar nada). `rareza_key` es hoy la normalización
   sin pérdida del valor de TCGdex, así que hace el papel de "valor crudo", y
   la categoría se deriva en código. Elegido por el propietario ("la opción
   que deje el código más limpio y eficiente"): cero migraciones, cero datos
   duplicados, remapear = editar `MAPA`, y el índice `cartas_rareza_key_index`
   sirve al `IN` del filtro sin cambios. El caso `excepciones` va por
   `NOT IN`, que en Postgres puede no usar el índice; sobre una tabla de
   miles de filas es irrelevante y a cambio atrapa las claves desconocidas.
2. **NULL se queda NULL.** El criterio "ninguna carta con `rareza_key` nula"
   chocaba con el cache-aside: 181 cartas locales (32,8 %) entraron por el
   listado de un set, que no trae rareza, y nadie las ha abierto. Marcarlas
   `excepciones` sería mentir. El criterio queda como "toda carta con detalle
   tiene una de las diez" (comprobado con `CatalogoTcg::RAREZAS` completo).
3. **Los nombres ES/EN viven en `lang/`, no en la clase.** Es donde el
   proyecto tiene todo texto visible y donde se añade un idioma nuevo; la
   clase los sirve con `nombre()`. Ponerlos en PHP habría sido la única
   excepción a esa regla.
4. **El símbolo en el `<option>` va sin color.** Un `<option>` nativo no
   admite HTML y el `color` por opción no es fiable entre navegadores; un
   listbox a medida por esto no compensa. El texto es "★★ Doble Rara", y el
   nombre al lado es quien carga el significado (la regla de accesibilidad
   de la spec). Por la misma razón, ahí no se puede aplicar `aria-hidden` al
   glifo: un lector leerá "estrella estrella Doble Rara" en el desplegable.
   En tarjetas y detalle sí va `aria-hidden`.
5. **Mapeo por símbolo impreso, no por nivel**: V/VMAX/VSTAR → `rara`,
   Radiant/Amazing → `excepciones`, Shiny según la era SV. Lo decidió el
   propietario al aceptar la propuesta del paso 0; los ⚠ están anotados en el
   comentario de `MAPA`.
6. **Criterio de "no consulta el catálogo español para una categoría sin texto
   en español"**: no existe ninguna categoría así (todas tienen al menos una
   clave con texto español). El test comprueba lo que sí ocurre: a `/v2/es` se
   le mandan solo las claves que existen en español (para `rara`, cinco de
   nueve) y a `/v2/en` las nueve.
7. **Efecto colateral corregido**: la búsqueda en vivo mandaba `rarity=Rare`
   en modo laxo (subcadena) y en `sv03` devolvía 64 cartas en vez de 10. Ahora
   va con `eq:`.

## Datos (paso 0, BD local)

552 cartas; 371 con rareza. Tras el mapeo: comun 108 · infrecuente 91 ·
rara 99 · doble_rara 12 · ace_spec 0 · ilustracion 16 · ultra_rara 30 ·
ilustracion_especial 7 · hiper_rara 4 · **excepciones 4 (1,1 %)**. TCGdex sirve
hoy 42 rarezas (`/v2/en/rarities`); `Futuristic Rare` y `Pikachu Rare` no están
en `CatalogoTcg` y son el primer caso real de "clave desconocida → excepciones".

## Archivos tocados

- `api/app/Support/Rarezas.php` — nuevo
- `api/app/Support/CatalogoTcg.php`, `api/app/Models/Carta.php`,
  `api/app/Services/HidratadorDeCartas.php`, `api/app/Services/TcgdexService.php`,
  `api/app/Http/Controllers/CartaController.php`, `api/app/Http/Controllers/SetController.php`
- `api/lang/es/tcg.php`, `api/lang/en/tcg.php`
- `api/tests/Feature/RarezasTest.php` (nuevo), `BusquedaGlobalTest.php`,
  `CatalogoTcgTest.php`, `DatosPorIdiomaTest.php`
- `frontend/js/utils.js`, `frontend/js/filtros-catalogo.js`, `frontend/js/detalle-carta.js`,
  `frontend/css/estilos.css`
- `tests-e2e/tests/rarezas.spec.mjs` — nuevo

## Verificación

- `composer test` → **160 passed** (153 + 7), 772 aserciones.
- `npx playwright test tests/rarezas.spec.mjs tests/caminos-criticos.spec.mjs
  tests/inventario.spec.mjs` → **8 passed**. (`verificacion-correo.spec.mjs`
  no puede pasar en local con `MAIL_MAILER=smtp`, ya documentado.)
- Criterios, uno a uno: mapeo de las 15 claves reales + las de TCGdex sin
  cartas (`test_el_mapeo_de_las_rarezas_reales_del_inventario`); rareza
  inventada hidratada → guardada como slug, `excepciones`, "Excepciones"/
  "Other", y `?rareza=excepciones` la devuelve; `/cartas/filtros` en orden
  canónico y solo las presentes, con símbolo; `?rareza=excepciones` devuelve
  promo + radiante y nada más, `?rareza=rara` incluye `holo-rare-v`; búsqueda
  en vivo con `eq:A|B|…`; nombres en ES y EN; e2e del desplegable (orden y
  glifos) y del detalle (`.rareza-simbolo[aria-hidden]` + nombre, ES y EN,
  último elemento de la columna).
- Comprobación visual con capturas (detalle en claro con ★★ doradas, en
  oscuro con ★★ plateadas, y móvil oscuro con "Excepciones" sin símbolo):
  símbolo + nombre abajo a la izquierda en los tres.
- Ningún `package.json` bajo `frontend/`; ninguna imagen nueva.

## Pendiente

- Nada. Si más adelante se quiere el símbolo en las tarjetas de los grids,
  es una línea en `tarjetaCarta()` con `simboloRareza(carta.rareza_simbolo)`.
