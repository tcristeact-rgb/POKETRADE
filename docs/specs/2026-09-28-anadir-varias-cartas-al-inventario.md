---
estado: implementada
fecha: 2026-09-28
origen: claude-code
---

# Añadir varias cartas distintas al inventario de una vez

## Problema

En "Mi inventario", el modal "Añadir carta" solo admite una carta por vez:
al pulsar otra carta del catálogo se sustituye la anterior, y al confirmar el
modal se cierra. Para meter cinco cartas distintas hay que abrir el modal,
buscar, elegir y confirmar cinco veces.

## Decisión

El modal pasa a ser de **selección múltiple**: cada carta del catálogo se
marca o desmarca con un clic, las elegidas se listan debajo con su propia
cantidad, y un único botón las añade todas.

### Frontend

- Estado: un `Map` id → `{ carta, cantidad }`. Guarda la carta entera porque
  la selección **sobrevive a los cambios de búsqueda** (la carta deja de estar
  en los resultados visibles y hace falta su nombre para la fila).
- **Catálogo del modal**: clic, Enter o Espacio sobre una carta la añade con
  cantidad 1 o la quita si ya estaba. Las seleccionadas llevan la clase
  `seleccionada` y `aria-pressed="true"`, también al re-renderizar tras una
  búsqueda nueva.
- **Panel de selección**: una fila por carta con nombre, − / cantidad / +
  (1–99, como antes) y un botón ✕ para quitarla. Los `aria-label` de los
  botones llevan el nombre de la carta, porque ahora hay varios iguales.
  Al quitar una fila, el foco pasa al botón de confirmar (o al buscador si
  la selección queda vacía) en vez de caer al `body`.
- **Botón de confirmar**: el texto dice cuántas son ("Añadir 3 cartas al
  inventario", con plural por `Intl.PluralRules`). Se deshabilita mientras
  se envía para evitar un doble envío.
- **Envío**: una petición `POST /api/inventario` por carta, en serie.
  - Todas bien → se cierra el modal, alerta "N cartas añadidas" y se recarga
    el inventario.
  - Alguna falla → las que entraron salen de la selección y se recarga el
    inventario; las que fallaron siguen seleccionadas, el modal sigue abierto
    y la alerta dice cuántas entraron y, por cada fallo, "nombre: motivo".
- Al cerrar el modal se vacía la selección.
- El título del modal pasa a plural: "Añadir cartas al inventario".
- i18n ES/EN para todo texto nuevo.

### Backend

Sin cambios. `POST /api/inventario` ya valida carta y tope por petición.

## Alternativas descartadas

- **Endpoint por lotes (`POST /inventario` con un array)**: sería atómico y
  una sola petición, pero toca el controlador, que está en medio de la fase 0
  (fuente/sumidero declarados, tope por carta) con cambios sin commitear, y
  obliga a decidir la semántica de fallo parcial en el servidor. Para
  selecciones de unas pocas cartas, N peticiones en serie son aceptables y el
  endpoint actual ya aplica sus reglas carta a carta.
- **Peticiones en paralelo**: más rápido, pero en local `php artisan serve`
  es monohilo y no gana nada, y en serie el orden de los fallos es
  determinista.

## Criterios de aceptación

- [ ] `node --check` pasa en `frontend/js/inventario.js`, `frontend/js/i18n/es.js`
      y `frontend/js/i18n/en.js`.
- [ ] Las claves nuevas (`inv.quitarSeleccion`, `inv.anadirSeleccion`,
      `inv.anadidas`, `inv.errorAnadir`) existen en ES y en EN.
- [ ] En la app local, con sesión iniciada: pulsar 3 cartas del modal deja 3
      filas; volver a pulsar una deja 2.
- [ ] Subir la cantidad de una fila con + se refleja en la petición enviada.
- [ ] Cambiar el texto de búsqueda no borra la selección, y se puede añadir
      una carta de la búsqueda nueva (3 filas en total).
- [ ] El botón muestra "Añadir 3 cartas al inventario".
- [ ] Al confirmar salen 3 `POST /inventario` con los `carta_id` y cantidades
      elegidos, el modal se cierra y el inventario gana 3 cartas.
- [ ] Sin errores en la consola del navegador durante todo el flujo.

## Archivos afectados (previsión)

- `frontend/js/inventario.js`
- `frontend/pages/inventario.html`
- `frontend/css/estilos.css`
- `frontend/js/i18n/es.js`, `frontend/js/i18n/en.js`

## Fuera de alcance

- Endpoint por lotes o transaccional en el backend.
- Cambios en el catálogo del modal (paginación, filtros, imágenes).
- Selección múltiple en otros modales (publicar tradeo ya la tiene).
