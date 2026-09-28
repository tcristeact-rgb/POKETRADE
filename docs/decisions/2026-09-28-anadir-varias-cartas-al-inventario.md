---
spec: docs/specs/2026-09-28-anadir-varias-cartas-al-inventario.md
fecha: 2026-09-28
resultado: completo
---

# Añadir varias cartas distintas al inventario de una vez

## Qué se implementó

El modal "Añadir carta" de "Mi inventario" admite selección múltiple: cada
carta del catálogo se marca o desmarca con un clic, las elegidas aparecen en
filas con su cantidad (− / + / ✕) y un solo botón ("Añadir N cartas al
inventario") las envía. La selección se mantiene al cambiar la búsqueda. El
envío es una petición `POST /api/inventario` por carta, en serie; si alguna
falla, las que entraron salen de la selección y las fallidas se quedan para
reintentar, con una alerta que dice cuáles y por qué. Backend sin cambios.

## Desviaciones respecto a la spec

- **La spec se escribió después de implementar**, no antes. El usuario pidió
  el cambio directamente ("haz eso y nada más") y la spec y este registro se
  escribieron a posteriori, cuando los pidió. La spec describe lo
  implementado; no hubo un diseño previo con el que comparar.
- Por lo demás, ninguna: todo lo que dice la spec está hecho tal cual.

## Archivos tocados

- `frontend/js/inventario.js` — `Map` de selección, `alternarCarta`,
  `cambiarCantidad(id, delta)`, `renderizarSeleccion`, envío en serie con
  gestión de fallo parcial; el catálogo marca las seleccionadas con
  `aria-pressed`; `cerrarModal` vacía la selección.
- `frontend/pages/inventario.html` — el panel de una sola carta pasa a ser
  `<ul id="lista-seleccion">` + botón de confirmar; título en plural.
- `frontend/css/estilos.css` — `.lista-seleccion`, `.fila-seleccion`,
  `.fila-seleccion-nombre`, `.btn-quitar-seleccion` (con tokens del tema,
  válido en claro y oscuro).
- `frontend/js/i18n/es.js`, `frontend/js/i18n/en.js` — título en plural,
  `inv.disminuir` / `inv.aumentar` con `{nombre}`, y claves nuevas
  `inv.quitarSeleccion`, `inv.anadirSeleccion`, `inv.anadidas`,
  `inv.errorAnadir` (las tres últimas con plural).

## Verificación

Frontend servido con `node tools/servidor.mjs`, API con `php artisan serve`
y SQLite local; flujo recorrido con Playwright (Chromium headless) como el
usuario `teo@poketrade.es`.

- [x] `node --check` en los tres JS → sin errores.
- [x] Claves nuevas en ES y EN → importando ambos diccionarios, las cinco
      claves comprobadas existen en los dos.
- [x] 3 clics → 3 filas; volver a pulsar la tercera → 2 filas.
- [x] + dos veces en la primera fila → la petición salió con `"cantidad":3`.
- [x] Buscar "pikachu" y pulsar un resultado → 3 filas; la selección previa
      se conserva.
- [x] Texto del botón → "Añadir 3 cartas al inventario".
- [x] Confirmar → 3 POST: `{"carta_id":1,"cantidad":3}`,
      `{"carta_id":2,"cantidad":1}`, `{"carta_id":25,"cantidad":1}`; modal
      oculto; inventario de 2 a 5 cartas.
- [x] Consola del navegador → sin errores.

Las 3 cartas de prueba se borraron después del inventario local de Teo
(quedó con las 2 que tenía).

`composer test` no se ejecutó: el cambio no toca el backend.

## Pendiente

- El camino de fallo parcial (alguna carta rechazada por el tope) no se
  recorrió en el navegador; solo se revisó el código. Con el tope actual
  (999 copias por carta) no es fácil provocarlo desde la interfaz.
- Si el usuario cierra el modal mientras se envía, las cartas que aún no
  habían salido no se envían (la selección se vacía al cerrar). Las que ya
  salieron sí quedan añadidas y el inventario se recarga.
