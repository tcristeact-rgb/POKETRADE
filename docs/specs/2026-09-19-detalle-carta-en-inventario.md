---
estado: implementada
fecha: 2026-09-19
origen: claude-code
---

# Detalle resumido de una carta desde el inventario (modal)

## Problema

En "Mi inventario" cada carta es una tarjeta con imagen, nombre, tipo, rareza,
cantidad y el botón de eliminar. No hay forma de ver nada más (set, número,
PS, ilustrador, precio, descripción) sin salir a la ficha completa, y la
tarjeta ni siquiera enlaza a ella: la única acción es borrar.

(Una primera versión de esta spec afirmaba que `GET /api/inventario` tenía un
N+1 por el accessor `set_expansion`. Era falso: `Carta` declara
`protected $with = ['set']` y el set se carga en una sola consulta. Se
corrigió antes de implementar; queda un test que lo protege.)

## Decisión

Al pulsar una carta del inventario se abre un **modal resumido** con sus datos,
sin salir de la página y sin ninguna petición nueva (los datos ya vienen en
`GET /inventario`). Desde el modal se puede ampliar la ilustración (lightbox
existente) o ir a la ficha completa.

### Backend

- Sin cambios en la API: el listado ya trae todo lo que el modal enseña.
- Test nuevo `InventarioTest` (hoy no existe ninguno para `/inventario`): el
  listado devuelve `carta.set_expansion` con el nombre del set, y el número
  de consultas no crece con el número de cartas (con 5 cartas de sets
  distintos, ≤ 4 consultas), que es lo que garantiza el `$with` del modelo.

### Frontend

- **Tarjeta**: la imagen y el nombre pasan a estar dentro de un
  `<button class="carta-inventario-abrir" data-accion="detalle">` con
  `aria-label` "Ver detalles de {nombre}"; el botón "Eliminar" sigue fuera
  (nunca un control dentro de otro), como en `.tradeo-card-abrir` del
  marketplace. La cantidad sigue como badge.
- **Modal** `#modal-detalle-inv`, estático en `inventario.html`, con el mismo
  esqueleto y CSS que el modal de detalle del marketplace (`.modal-overlay` >
  `.modal-caja` > `.modal-header` / `.modal-contenido` / `.modal-footer`),
  `role="dialog" aria-modal="true" aria-labelledby` con el nombre de la carta
  como título. Se abre con `abrirModalAccesible` (foco, Tab atrapado, Escape,
  clic fuera) y se cierra devolviendo el foco a la tarjeta que lo abrió.
- **Contenido** ("resumido": lo que cabe sin scroll en un móvil):
  - ilustración (`imagen_high` con `imagen_low` de respaldo, o el dorso si no
    hay), dentro de un botón "Ampliar" que abre `abrirLightbox([carta])` —
    el lightbox ya se apila sobre modales;
  - lista de atributos, cada uno solo si tiene valor: tipo, rareza, set,
    número, PS, ilustración (ilustrador), precio medio (Cardmarket);
  - **cantidad en el inventario** ("En tu inventario: 3");
  - descripción, si la hay, en un párrafo.
- **Pie**: enlace "Ver ficha completa" a `detalle-carta.html?id={carta.id}`
  y botón "Cerrar". Eliminar se queda en la tarjeta: el modal es para mirar.
- Los datos salen del array `items` ya cargado: cero peticiones al abrir.
  Después de eliminar una carta, si su modal estaba abierto, se cierra.
- Textos por `t(...)`: se reutilizan las claves de la ficha
  (`carta.tipo`, `carta.rareza`, `carta.set`, `carta.numero`, `carta.ps`,
  `carta.ilustracion`, `carta.precioMedio`, `carta.fuentePrecio`,
  `carta.ampliar`) y se añaden `inv.verDetalle`, `inv.enTuInventario`,
  `inv.verFicha`. ES y EN.
- CSS: solo lo propio del cuerpo del modal (`.detalle-inv-*`); el resto es el
  del marketplace. Botón de la tarjeta con `:focus-visible` como el resto.

## Alternativas descartadas

- **Enlazar la tarjeta a la ficha completa** (`detalle-carta.html`): pierde el
  contexto del inventario y la cantidad; y para "mirar rápido" es un viaje de
  ida y vuelta con cold start posible.
- **Pedir `GET /cartas/{id}` al abrir**: no aporta nada que el listado no
  traiga ya, y añade una petición (y una hidratación desde TCGdex si la
  carta no tiene detalle en el idioma activo). Si más adelante hace falta la
  hidratación, se hace desde la ficha completa, que ya la tiene.
- **Modal nuevo con su propio CSS**: ya hay dos modales con el mismo
  esqueleto; un tercero distinto sería deuda.
- **Eliminar desde el modal**: dos sitios para la misma acción destructiva.

## Criterios de aceptación

**Backend** (`tests/Feature/InventarioTest.php`, nuevo)

- [ ] `GET /api/inventario` devuelve, en cada item, `carta.set_expansion`
      con el nombre del set de la carta.
- [ ] Con 5 cartas de 5 sets distintos en el inventario, la petición hace
      como máximo 4 consultas SQL (`DB::getQueryLog`).
- [ ] `composer test` en verde.

**Frontend** (`tests-e2e/`, un test; con la cuenta de demo `teo@poketrade.es`)

- [ ] En `inventario.html`, cada tarjeta tiene un botón con
      `aria-label` "Ver detalles de <nombre>" y el de eliminar no está dentro.
- [ ] Pulsar la tarjeta abre un `role="dialog"` cuyo título es el nombre de la
      carta, con la cantidad del inventario visible y sin ninguna petición a
      `/api/` durante la apertura.
- [ ] El enlace "Ver ficha completa" apunta a `detalle-carta.html?id=<id>`.
- [ ] Escape cierra el modal y el foco vuelve a la tarjeta.
- [ ] Textos nuevos en ES y EN; ningún `package.json` bajo `frontend/`.

## Archivos afectados (previsión)

- `api/tests/Feature/InventarioTest.php` — nuevo
- `frontend/pages/inventario.html` — modal estático
- `frontend/js/inventario.js` — botón en la tarjeta, abrir/cerrar/rellenar el modal, lightbox
- `frontend/css/estilos.css` — `.carta-inventario-abrir`, `.detalle-inv-*`
- `frontend/js/i18n/{es,en}.js` — 3 claves
- `tests-e2e/tests/inventario.spec.mjs` — nuevo

## Fuera de alcance

- Editar la cantidad desde el modal.
- Hidratar el detalle desde TCGdex al abrir.
- Cambiar el modal de "Añadir carta" del inventario.
