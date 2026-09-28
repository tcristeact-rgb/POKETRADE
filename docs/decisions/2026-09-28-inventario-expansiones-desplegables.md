---
spec: docs/specs/2026-09-28-inventario-expansiones-desplegables.md
fecha: 2026-09-28
resultado: completo
---

# Expansiones del inventario como filas desplegables

## Qué se implementó

En la vista "Por expansión", cada set es ahora un `<details class="grupo-set">`.
Su `<summary>` contiene el `<h2>` (símbolo, nombre y "N cartas · M copias")
y una flecha dibujada con CSS que gira al abrir. Cómo se abre cada fila:

- Sin búsqueda, todas salen plegadas, salvo las que el usuario ha abierto.
  Esas se guardan en un `Set` en memoria (`setsAbiertos`), con el
  `tcgdex_id` del set como clave.
- Con texto buscado, las filas con coincidencias salen abiertas.
- Un listener de `toggle` en fase de captura anota lo que abre o cierra el
  usuario. Lo ignora mientras hay texto buscado, porque un `<details>`
  pintado con `open` también dispara `toggle` y si no, la búsqueda "abriría"
  filas en nombre del usuario.

`agruparPorSet` devuelve ahora también la `clave` de cada grupo.

## Desviaciones respecto a la spec

Ninguna.

## Archivos tocados

- `frontend/js/inventario.js`: `setsAbiertos`, el listener de `toggle`, el
  marcado de `<details>`/`<summary>` y la `clave` en `agruparPorSet`.
- `frontend/css/estilos.css`: `.grupo-set` como tarjeta,
  `.grupo-set-cabecera` (sin marcador nativo, con hover y `:focus-visible`),
  `.grupo-set-flecha` y el padding del grid interior. La transición del
  giro la anula la regla global de `prefers-reduced-motion`.

## Verificación

App local (`php artisan serve` + `node tools/servidor.mjs`), con Playwright
como `teo@poketrade.es`. Se le añadieron cartas de prueba de 4 sets y
después se borraron: quedó con las 2 que tenía (ids 3 y 6).

- [x] `node --check js/inventario.js` → sin errores.
- [x] Estado inicial → `▶151 ▶Cenit Supremo ▶Celebraciones ▶Skyridge`,
      0 cartas visibles (`checkVisibility()`) y 4 flechas.
- [x] Clic en la 1ª fila → `▼151` y 4 cartas visibles; otro clic → plegada.
      Foco en la 2ª cabecera + Enter → `▼Cenit Supremo`.
- [x] Buscar "pikachu" → `▼151 ▼Celebraciones`, las dos con coincidencias
      y abiertas. Buscar "a" → las 4 abiertas. Vaciar la búsqueda →
      `▶151 ▼Cenit Supremo ▶Celebraciones ▶Skyridge`: vuelve al estado del
      usuario y la búsqueda no dejó nada abierto.
- [x] Fila abierta tras recargar el inventario: con "151" abierta se borró
      una carta suya → la fila siguió abierta (`▼151 3 cartas · 3 copias`).
- [x] Peticiones a `/api` al abrir, cerrar y buscar → ninguna.
- [x] Móvil 390 px en tema oscuro → sin scroll horizontal; filas legibles
      (captura revisada).
- [x] Consola → sin errores.

Nota sobre la verificación: la primera versión del script daba por
visibles las cartas de filas plegadas porque usaba `offsetParent`. En
Chrome, el contenido de un `<details>` cerrado se oculta con
`content-visibility` y `offsetParent` no pasa a `null`. Se cambió a
`checkVisibility()`. También se rehízo la prueba de "sigue abierta tras
eliminar": la primera borró la única carta de su set y la fila desapareció,
así que no demostraba nada.

## Pendiente

- Nada de lo especificado.
