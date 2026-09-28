---
spec: docs/specs/2026-09-28-inventario-por-expansion-y-busqueda.md
fecha: 2026-09-28
resultado: completo
---

# Inventario agrupado por expansión y búsqueda en el propio inventario

## Qué se implementó

Sobre el grid de "Mi inventario" hay ahora una barra con tres cosas:

- un buscador del propio inventario;
- un selector de vista "Todas" / "Por expansión";
- un contador de cartas.

La vista agrupada pinta una sección por set, con su símbolo, su nombre y
"N cartas · M copias". Los sets van del más reciente al más antiguo y, dentro
de cada uno, las cartas van por número natural. La búsqueda filtra en las dos
vistas sin distinguir mayúsculas ni tildes, y combina las palabras con Y.
Todo se resuelve en el navegador. Del backend solo cambió `GET /inventario`,
que ahora serializa `carta.set` completo (`makeVisible('set')`), sin
consultas nuevas.

## Desviaciones respecto a la spec

- **Símbolos de set rotos.** La spec no lo contemplaba, pero TCGdex no tiene
  símbolo para algunos sets: para `sv03.5` ("151") la URL responde 400 y
  Chrome la bloquea (`ERR_BLOCKED_BY_ORB`). Dejaba un hueco en blanco delante
  del nombre. Se añadió un listener de `error` en fase de captura que oculta
  el `<img>`, igual que hace el catálogo con `.ph-logo-simbolo`. No se
  reutilizó `activarPlaceholderImagenes` porque también sustituye otras
  imágenes (logos) con una lógica que el inventario no necesita.
- **Selector de vista en móvil.** En la primera captura a 390 px, la barra
  estiraba el grupo a todo el ancho, pero los botones no lo llenaban. Se
  corrigió con `flex: 1` en los botones. Es un detalle de estilo y no cambia
  lo especificado.
- Por lo demás, ninguna.

## Archivos tocados

- `api/app/Http/Controllers/InventarioController.php`: `index()` hace
  visible `carta.set`. **Ojo al hacer commit:** este archivo también lleva
  los cambios de la fase 0, que siguen sin commitear. El hunk de esta spec
  es solo el de `index()`.
- `api/tests/Feature/InventarioTest.php`: test nuevo
  `test_el_listado_trae_el_set_entero_para_agrupar_por_expansion`. Mismo
  aviso: el archivo también tiene los tests de la fase 0 sin commitear.
- `frontend/pages/inventario.html`: la barra `#barra-inventario`, con input,
  `.vista-toggle` y contador.
- `frontend/js/inventario.js`: `renderizarInventario()` pasa a leer el
  estado (vista, texto) en vez de recibir los items. Se añaden
  `tarjetaInventario`, `filtrarInventario`, `agruparPorSet`, `cambiarVista`,
  `marcarVista`, `limpiarBusqueda`, la vista guardada en `localStorage` y el
  listener de símbolos rotos.
- `frontend/css/estilos.css`: `.filtros-inventario`, `.vista-toggle`,
  `.grupos-inventario` y `.grupo-set-*`, con los tokens del tema.
- `frontend/js/i18n/es.js`, `frontend/js/i18n/en.js`: 13 claves nuevas.

## Verificación

App local (`php artisan serve` + `node tools/servidor.mjs`, SQLite),
recorrida con Playwright como `teo@poketrade.es`. Para la prueba se le
añadieron 5 cartas de 4 sets por la API y después se borraron: quedó con las
2 que tenía.

- [x] `composer test` → 191 passed, incluidos el test nuevo y el de
      consultas (≤ 4).
- [x] `node --check` en `inventario.js`, `es.js` y `en.js` → sin errores.
- [x] Claves i18n: un script extrajo las 41 claves `inv.*` que usan
      `inventario.js` e `inventario.html`, y no falta ninguna ni en ES ni
      en EN.
- [x] Vista por expansión → 4 secciones en orden: 151 (2023-09-22),
      Cenit Supremo (2023-01-20), Celebraciones (2021-10-08) y Skyridge
      (2003-05-12). Resúmenes: "4 cartas · 5 copias", "1 carta · 1 copia",
      "1 carta · 1 copia" y "1 carta · 3 copias", que coinciden con las
      cantidades dadas de alta.
- [x] Orden por número dentro de 151 → Venusaur ex (3), Charizard ex (6),
      Caterpie (10), Pikachu (25).
- [x] Recargar la página → sigue en "Por expansión" con 4 secciones.
- [x] "poke ball" → encuentra "Poké Ball", contador "1 de 7 cartas".
      "ex 151" → Venusaur ex y Charizard ex, "2 de 7 cartas".
- [x] "zzz" → "Ninguna carta de tu inventario coincide con «zzz»". El botón
      "Limpiar búsqueda" devuelve las 7 cartas y deja el foco en el buscador.
- [x] Peticiones a `/api` mientras se buscaba y se cambiaba de vista →
      ninguna.
- [x] En inglés (`/en/`): "Search your inventory...", "All / By expansion",
      "4 cards · 5 copies", "7 cards".
- [x] Móvil (390 px) en tema oscuro → sin scroll horizontal y el selector
      llena el ancho.
- [x] Símbolo roto (151) → queda oculto (`hidden`).
- [x] Consola del navegador → sin errores en ningún recorrido.

## Pendiente

- Nada de lo especificado. Plegar o desplegar secciones y otros filtros
  quedaron fuera de alcance en la spec.
