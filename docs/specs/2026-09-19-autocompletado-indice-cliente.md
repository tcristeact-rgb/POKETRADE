---
estado: implementada
fecha: 2026-09-19
origen: claude-code
---

# Autocompletado de los buscadores con un índice de nombres en el cliente

Sustituye a `2026-09-12-autocompletado-buscador.md` (propuesta, no implementada).
De aquella se conserva todo lo que no depende de la fuente de datos: un módulo
único `frontend/js/autocompletado.js`, sugerencias como **términos** deduplicados
por nombre (no cartas), combobox accesible con teclado, tres montajes (header,
drawer móvil, filtro del catálogo), sin dependencias. Cambia de dónde salen los
nombres, y con ello el comportamiento visible: sugiere **desde la primera letra**
y **sin ninguna petición por tecla**.

## Problema

Los tres buscadores no ayudan mientras se escribe: hasta que no navegas no sabes
si `pik` es algo. La spec anterior proponía pedir sugerencias a `/api/cartas/buscar`
en cada pausa de tecleo, es decir, un viaje a TCGdex por sugerencia: 0,8-3 s
medidos, mínimo 2 letras, dependiente de TCGdex y del cold start de Render, y
chocando con el limiter de `buscar` (30/min por IP).

Dato que cambia el diseño, medido el 2026-09-19: el catálogo completo son 23.736
cartas (2,3 MB), pero solo **4.619 nombres únicos** en inglés y 3.337 en español:
**69 KB de JSON, 25 KB gzip** por idioma. Menos que `estilos.css`. La alternativa
"cachear el catálogo en el cliente" que la spec anterior descartó lo hacía
pensando en 20k cartas, no en 4,6k nombres.

## Decisión

### Backend: `GET /api/cartas/nombres?idioma=es|en`

- Devuelve un array JSON de nombres únicos, ordenados, del idioma pedido
  (`idioma` opcional; por defecto `Idiomas::activo()`; valor no soportado → 422).
- Para `es`, la lista es la **unión** de los nombres del catálogo español y del
  inglés (el catálogo mostrado también cae al inglés cuando no hay español, y
  `buscarCartas` busca en los dos): quien escribe `pik` en español debe ver
  `Pikachu` aunque esa carta solo exista en inglés.
- Se construye desde `TCGdex /v2/{idioma}/cards` (2,3 MB) **una vez al día**:
  `TcgdexService::nombresDeCartas($idioma)` cachea la lista derivada 24 h
  (`tcgdex:nombres:{idioma}`) y guarda además una copia sin caducidad
  (`…:ultimo`) que se sirve si TCGdex no responde al refrescar. Sin ninguna de
  las dos → 503 `tcgdex_caido`, como el resto. La respuesta cruda de 2,3 MB no
  se cachea 24 h (solo la lista): pasa por `get()` con TTL corto.
- Cabeceras: `Cache-Control: public, max-age=86400` y `ETag`, para que el
  navegador no vuelva a bajarla en la misma sesión ni en las siguientes 24 h.
- La ruta va **antes** de `/cartas/{id}` (regla de CLAUDE.md). Sin throttle
  propio: es una descarga por sesión y el limiter general (120/min) basta.

### Frontend: `frontend/js/autocompletado.js`

- `montarAutocompletado(input, { alElegir })`: convierte el input en un
  `role="combobox"` (`aria-autocomplete="list"`, `aria-expanded`,
  `aria-controls`, `aria-activedescendant`) con un `<ul role="listbox">` debajo.
- La lista de nombres se descarga **al primer `focus`** de cualquier buscador de
  la página (una sola promesa compartida), nunca en la carga de la página. Hasta
  que llega, el buscador funciona como hoy (sin lista).
- Filtrado en memoria, síncrono, desde **1 carácter**: normalización NFD sin
  diacríticos y sin mayúsculas en ambos lados; orden de relevancia: empieza por
  el término → una palabra empieza por el término → contiene; empate por orden
  alfabético (la lista ya viene ordenada). Máximo 8 sugerencias.
- Teclado: ↓/↑ recorren, Enter selecciona la resaltada (y detiene la propagación
  para que el handler de Enter del header no navegue dos veces), Escape cierra;
  clic fuera cierra; clic en una opción selecciona. Al seleccionar: el input
  toma el nombre y se llama a `alElegir(nombre)`.
- Montajes: `header.js` (`#buscador` → `lanzarBusqueda`), `header.js`
  (`#buscador-drawer`, ídem), `filtros-catalogo.js` (`#filtro-nombre` → dispara
  el `input` que ya aplica el filtro a la URL). El marketplace busca tradeos, no
  cartas: fuera.
- Textos por `t(...)`: `busqueda.sugerenciasAria` (ES/EN).
- CSS: `.autocompletado-lista` / `.autocompletado-opcion` con los tokens del
  tema; la lista se posiciona bajo el input con `position: absolute` respecto a
  su contenedor (`.buscador-contenedor` pasa a `position: relative`; `.filtros`
  ya es sticky, que posiciona).

## Alternativas descartadas

- **Sugerencias en vivo desde `/cartas/buscar`** (spec anterior): latencia de
  0,8-3 s por tecla, mínimo 2 letras, dependencia de TCGdex y del limiter.
- **Híbrido BD local + TCGdex**: dos fuentes, lista que "salta" al llegar la
  segunda, y en producción la BD local está casi vacía al principio, así que
  casi siempre acaba en TCGdex.
- **Descargar la lista en la carga de la página**: son 25 KB en cada página;
  al primer foco solo la paga quien va a buscar.
- **Comando programado para refrescar**: Render free no tiene scheduler. La
  caché perezosa de 24 h con copia de respaldo cubre lo mismo.
- **Sugerir sets y series**: la búsqueda global navega a cartas; mezclar
  entidades en la lista exigiría destinos distintos. Otra spec.

## Criterios de aceptación

**Backend** (`tests/Feature/NombresDeCartasTest.php`)

- [ ] `GET /api/cartas/nombres?idioma=en` con TCGdex fakeado devuelve 200 con un
      array de nombres únicos y ordenados, sin repetir `Pikachu` aunque haya
      varias cartas con ese nombre.
- [ ] `?idioma=es` une los nombres españoles con los ingleses, sin duplicados.
- [ ] Sin `idioma`, usa `Accept-Language`.
- [ ] `?idioma=xx` → 422.
- [ ] La respuesta lleva `Cache-Control` con `max-age=86400` y `ETag`.
- [ ] Segunda petición en 24 h: no vuelve a llamar a TCGdex (`Http::assertSentCount`).
- [ ] TCGdex caído y sin copia previa → 503; con copia previa → 200 con la copia.
- [ ] `/cartas/nombres` no se interpreta como `/cartas/{id}`.
- [ ] `composer test` en verde.

**Frontend** (`tests-e2e/`, un test nuevo)

- [ ] Escribir `pik` en `#buscador` muestra una lista con `Pikachu` entre las
      opciones, y ninguna opción repetida.
- [ ] Con **una** letra ya hay sugerencias.
- [ ] Mientras se teclea no se hace **ninguna** petición a `/api/cartas/buscar`;
      `/api/cartas/nombres` se pide **una** vez.
- [ ] ↓ + Enter navega a `pages/catalogo.html?q=<nombre elegido>`.
- [ ] Escape cierra la lista y `aria-expanded` pasa a `false`.
- [ ] El input declara `role="combobox"`; la lista, `role="listbox"` y opciones
      `role="option"`.
- [ ] `git diff --stat` no muestra `package.json` ni `node_modules` bajo `frontend/`.
- [ ] Textos nuevos por `t(...)` en ES y EN.

## Archivos afectados (previsión)

- `api/app/Services/TcgdexService.php` — `nombresDeCartas()`
- `api/app/Http/Controllers/CartaController.php` — `nombres()`
- `api/routes/api.php` — ruta antes de `{id}`
- `api/tests/Feature/NombresDeCartasTest.php` — nuevo
- `frontend/js/autocompletado.js` — nuevo
- `frontend/js/header.js`, `frontend/js/filtros-catalogo.js` — montaje
- `frontend/js/i18n/es.js`, `en.js` — una clave
- `frontend/css/estilos.css` — estilos de la lista, `position: relative` en `.buscador-contenedor`
- `tests-e2e/tests/caminos-criticos.spec.mjs` — un test

## Fuera de alcance

- Sugerir sets/series; sugerencias con imagen; marketplace.
- Refresco programado del índice.
- `limit` en `/cartas/buscar` (criterio de la spec anterior; ya no hace falta).
