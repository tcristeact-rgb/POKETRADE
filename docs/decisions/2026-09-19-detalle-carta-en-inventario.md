---
spec: docs/specs/2026-09-19-detalle-carta-en-inventario.md
fecha: 2026-09-19
resultado: completo
---

# Detalle resumido de una carta desde el inventario (modal)

## Qué se implementó

- **Backend**: sin cambios en la API. Test nuevo `InventarioTest` (2):
  el listado trae `carta.set_expansion` con el nombre del set, y con 5
  cartas de 5 sets distintos la petición hace ≤ 4 consultas (`DB::enableQueryLog`).
- **Tarjeta** (`inventario.js`): imagen, nombre, tipo y rareza van dentro de
  `<button class="carta-inventario-abrir" data-accion="detalle">` con
  `aria-label` "Ver detalles de {nombre}"; el botón "Eliminar" queda fuera.
  El clic se resuelve por delegación en el grid, como el de eliminar.
- **Modal** `#modal-detalle-inv`, estático en `inventario.html`, con el
  esqueleto y CSS del modal del marketplace (`.modal-overlay` > `.modal-caja` >
  header / contenido / footer), `role="dialog" aria-modal="true"
  aria-labelledby` con el nombre de la carta. Abre con `abrirModalAccesible`
  (foco, Tab atrapado, Escape, clic fuera, foco de vuelta a la tarjeta).
- **Contenido**: ilustración (`imagen_high` → `imagen_low` → dorso) dentro de
  un botón "Ampliar" que llama a `abrirLightbox([carta])`; `<dl>` con tipo,
  rareza, set, número, PS, ilustración y precio medio (solo los que tienen
  valor); badge "En tu inventario: N"; descripción si la hay.
- **Pie**: "Cerrar" y enlace "Ver ficha completa" a `detalle-carta.html?id=<id>`.
- Los datos salen del array `itemsInventario` ya cargado: **cero peticiones al
  abrir**. Si se elimina la carta cuyo modal está abierto, el modal se cierra.
- **CSS**: `.carta-inventario-abrir` (reset de botón + `:focus-visible` con
  `--anillo-foco`) y sección `.detalle-inv-*` (grid 220px + 1fr, apilado
  bajo 600px).
- **i18n**: `inv.verDetalle`, `inv.enTuInventario`, `inv.verFicha`,
  `inv.numero` en ES y EN.
- **e2e**: `tests-e2e/tests/inventario.spec.mjs` (1 test, cuenta de demo).

## Desviaciones respecto a la spec

1. **La premisa del N+1 era falsa y se corrigió antes de implementar.** La
   primera redacción de la spec proponía cargar `carta.set` en el controlador.
   El test de sabotaje no se puso en rojo: `Carta` declara
   `protected $with = ['set']`, así que el set ya viene en una consulta. Se
   revirtió el cambio en `InventarioController`, se reformularon los dos
   tests como protección del `$with` y se corrigió la spec (aún sin código
   escrito, no a posteriori). La spec deja constancia en su primer apartado.
2. **Cuatro claves i18n, no tres.** La etiqueta "Número" de la ficha
   (`carta.numero`) lleva el valor interpolado ("Nº {numero}"), no sirve como
   rótulo de un `<dt>`. Se añadió `inv.numero` en vez de recortar la cadena
   con un `replace`.
3. **`inventario.js` pasó de CRLF a LF.** Estaba commiteado con CRLF y algún
   CR suelto (git lo trataba como binario, `i/-text`); al normalizarlo el diff
   es el archivo entero. Mismo tratamiento que se dio a `perfil.js` en una
   sesión anterior.

## Fuera de la spec, pero necesario para verificar

- Las cuentas de demo de la BD de desarrollo (`teo@`, `maria@`) todavía
  tenían la contraseña antigua `123456`; se reajustaron a `12345678` (la del
  seeder) para que el e2e entre. Solo afecta a la BD local, no al repo.
- El e2e de verificación de correo no puede pasar en local mientras `api/.env`
  tenga `MAIL_MAILER=smtp` (ya documentado en la decisión de verificación);
  por eso se corrió solo `tests/inventario.spec.mjs`.

## Archivos tocados

- `api/tests/Feature/InventarioTest.php` — nuevo
- `frontend/pages/inventario.html` — modal estático
- `frontend/js/inventario.js` — botón en la tarjeta, `abrirDetalle` / `cerrarDetalle`, lightbox, cierre al eliminar
- `frontend/css/estilos.css` — `.carta-inventario-abrir`, sección `.detalle-inv-*`
- `frontend/js/i18n/es.js`, `en.js` — 4 claves
- `tests-e2e/tests/inventario.spec.mjs` — nuevo

## Verificación

- `composer test` → **153 passed** (151 + 2), 718 aserciones.
- `npx playwright test tests/inventario.spec.mjs` → **1 passed**: aria-label
  "Ver detalles de <nombre>", ningún `.btn-eliminar` dentro del botón,
  `role="dialog"`, título = nombre, "En tu inventario:" visible, `<dt>` > 0,
  href `detalle-carta.html?id=\d+`, cero peticiones a `/api/` al abrir,
  Escape cierra y `document.activeElement` es la tarjeta.
- Comprobación visual con capturas (escritorio claro y móvil oscuro): imagen,
  atributos, badge y pie correctos; en móvil el pie apila "Ver ficha
  completa" sobre "Cerrar". El lightbox se apila sobre el modal y el primer
  Escape cierra el lightbox, el segundo el modal.
- Ningún `package.json` bajo `frontend/`.

## Pendiente

- Nada.
