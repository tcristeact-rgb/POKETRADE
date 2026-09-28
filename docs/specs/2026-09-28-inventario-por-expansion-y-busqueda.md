---
estado: implementada
fecha: 2026-09-28
origen: claude-code
---

# Inventario agrupado por expansión y búsqueda en el propio inventario

## Problema

"Mi inventario" es un único grid con las cartas en orden de alta. Con unas
decenas de cartas de sets distintos no hay manera de ver qué se tiene de cada
expansión, y para encontrar una carta concreta hay que recorrer el grid entero
con la vista: no existe búsqueda dentro del propio inventario (el buscador del
header busca en el catálogo, no en lo que uno tiene).

## Decisión

Una barra sobre el grid con dos cosas:

1. **Selector de vista** "Todas" / "Por expansión". "Todas" es el grid de hoy;
   "Por expansión" agrupa las cartas en una sección por set, con los sets del
   más reciente al más antiguo y las cartas de cada set por su número.
2. **Buscador del propio inventario**, que filtra al instante en cualquiera de
   las dos vistas.

Todo se resuelve en el navegador sobre lo que ya trae `GET /inventario`; lo
único que falta en esa respuesta es la fecha de lanzamiento y el símbolo del
set, y se añaden en el backend.

### Backend

- `GET /api/inventario` serializa también `carta.set` (el modelo `Set` tal
  cual: `nombre`, `simbolo`, `logo`, `fecha_lanzamiento`, …). Hoy `Carta`
  lo oculta con `$hidden` porque el resto de la app solo necesita el nombre;
  aquí se hace visible con `makeVisible('set')` solo en este endpoint.
  El set ya se carga por el `$with` del modelo: **ninguna consulta nueva**.
- `InventarioTest`: el listado trae `carta.set.nombre` y
  `carta.set.fecha_lanzamiento`, y el test de número de consultas sigue en ≤ 4.

### Frontend

- **Barra** `.filtros` (el mismo componente que catálogo y marketplace, sticky
  bajo el header) con `role="search"`: input de búsqueda, selector de vista y
  contador. Se oculta cuando el inventario está vacío.
- **Selector de vista**: dos botones en un `role="group"` con `aria-pressed`.
  La elección se recuerda en `localStorage` (preferencia de este navegador;
  lectura y escritura en `try/catch`, y sin ella se usa "Todas").
- **Vista "Por expansión"**:
  - una `<section>` por set con un `<h2>`: símbolo del set (si lo hay),
    nombre y resumen "N cartas · M copias";
  - sets ordenados por `fecha_lanzamiento` descendente; los que no tienen
    fecha, al final por nombre; las cartas sin set, en un grupo
    "Sin expansión" al final;
  - dentro de cada set, cartas por número con orden natural (`2` antes de
    `10`; `TG05` no rompe nada).
- **Vista "Todas"**: el grid de hoy, sin cambios de orden.
- **Búsqueda**:
  - filtra por nombre, expansión, tipo, rareza y número;
  - sin distinguir mayúsculas ni tildes ("pokemon" encuentra "Pokémon");
  - varias palabras se combinan con Y: "pikachu 151" deja los Pikachu del 151;
  - en la vista agrupada desaparecen los sets sin coincidencias;
  - cero coincidencias → mensaje con el texto buscado y un botón "Limpiar
    búsqueda";
  - contador: "N cartas", o "M de N cartas" mientras se filtra.
- Cambiar de vista o de texto **no hace peticiones**; eliminar o añadir cartas
  recarga el inventario y respeta la vista y el texto actuales.
- Las tarjetas son las mismas de hoy (detalle y eliminar siguen funcionando).
- i18n ES/EN para todo texto nuevo; tema claro/oscuro con los tokens
  existentes.

## Alternativas descartadas

- **Ordenar los sets por nombre o por `set_id`**: no requiere backend, pero
  ni el nombre ni el id de TCGdex siguen el orden cronológico, que es el que
  un coleccionista espera (el catálogo ya ordena así).
- **Pedir `/api/sets` desde el inventario para sacar las fechas**: una
  petición extra con los 167 sets para usar los pocos que tiene el usuario,
  cuando el dato ya se está cargando en el servidor y solo está oculto.
- **Añadir la fecha como atributo calculado de `Carta`**: engordaría todas
  las respuestas de cartas de la app (catálogo, búsqueda, tradeos) para un
  dato que solo usa el inventario.
- **Búsqueda contra el backend**: el inventario ya está entero en el
  navegador; una petición por tecla sería más lenta y no aporta nada.

## Criterios de aceptación

- [ ] `composer test` pasa, incluido el test nuevo de `carta.set` en
      `GET /inventario` y el de consultas (≤ 4).
- [ ] `node --check` pasa en los JS tocados.
- [ ] Las claves i18n nuevas existen en ES y en EN.
- [ ] En la app local, con un usuario con cartas de al menos 2 sets: la vista
      "Por expansión" muestra una sección por set, en orden de
      `fecha_lanzamiento` descendente, con la suma correcta de cartas y copias.
- [ ] Dentro de un set, las cartas salen por número natural.
- [ ] La vista elegida sobrevive a recargar la página.
- [ ] Buscar un nombre sin tilde encuentra la carta con tilde; buscar dos
      palabras (nombre + set) filtra por ambas; el contador dice "M de N".
- [ ] Una búsqueda sin coincidencias muestra el mensaje y "Limpiar búsqueda"
      devuelve todas las cartas.
- [ ] Cambiar de vista o escribir en el buscador no lanza ninguna petición a
      `/api`.
- [ ] Sin errores en la consola del navegador.

## Archivos afectados (previsión)

- `api/app/Http/Controllers/InventarioController.php` (`index`)
- `api/tests/Feature/InventarioTest.php`
- `frontend/pages/inventario.html`
- `frontend/js/inventario.js`
- `frontend/css/estilos.css`
- `frontend/js/i18n/es.js`, `frontend/js/i18n/en.js`

## Fuera de alcance

- Otros filtros (tipo, rareza en desplegable), otros criterios de orden, o
  plegar/desplegar secciones.
- Paginación del inventario.
- Buscar en el modal "Añadir carta" (ya busca en el catálogo).
