---
spec: docs/specs/2026-09-19-autocompletado-indice-cliente.md
fecha: 2026-09-19
resultado: completo
---

# Autocompletado de los buscadores con un índice de nombres en el cliente

## Qué se implementó

- `GET /api/cartas/nombres?idioma=es|en`: array JSON de nombres únicos y
  ordenados; `Cache-Control: public, max-age=86400` + `ETag`; 422 con idioma
  no soportado; 503 `tcgdex_caido` solo si no hay lista ni copia previa.
- `TcgdexService::nombresDeCartas()`: deriva la lista de `/v2/{idioma}/cards`
  (unión con el inglés para `es`), la cachea 24 h y guarda una copia sin
  caducidad que sirve si TCGdex no responde al refrescar. La respuesta cruda
  (2,3 MB) pasa por `get()` con TTL de 60 s: no se retiene.
- `frontend/js/autocompletado.js`: combobox WAI-ARIA (`role="combobox"`,
  `aria-expanded`, `aria-activedescendant`, listbox/option), índice compartido
  por página descargado una vez, filtrado síncrono desde 1 carácter con
  normalización NFD y relevancia prefijo > palabra > contiene, máximo 8.
- Montajes: header y drawer (`header.js`), filtro del catálogo
  (`filtros-catalogo.js`). Clave i18n `busqueda.sugerenciasAria` ES/EN.
- CSS: `.autocompletado-lista` / `.autocompletado-opcion` con tokens del tema;
  `.buscador-contenedor` pasa a `position: relative`.
- Tests: `NombresDeCartasTest` (9) y un test e2e en `tests-e2e/`.

## Desviaciones respecto a la spec

1. **Import dinámico al primer foco, no import estático.** La spec preveía
   que `header.js` importase el módulo. `header.js` está en el camino crítico
   del pintado (por eso cada página lleva `modulepreload` de su cascada) y un
   import estático más habría costado un viaje de ida y vuelta antes de pintar
   el header en las 18 páginas. `activarAutocompletado()` hace
   `import('./autocompletado.js')` en el primer `focus` (`{ once: true }`) y
   el módulo pide el índice nada más montarse. `filtros-catalogo.js` reutiliza
   ese helper del header.
2. **Enter con sugerencia resaltada**: el módulo hace `stopImmediatePropagation`,
   pero como se monta después de que el header registrara su propio `keydown`,
   ese orden no bastaba. El header comprueba `aria-activedescendant` y no
   navega si hay una sugerencia resaltada: acoplamiento a través del estado
   ARIA, que es público y observable.
3. **Tamaño real**: 6.219 nombres en `es` (unión con `en`), 105 KB / 32 KB gzip,
   no los 4.619 / 25 KB estimados (la estimación era solo inglés). En
   producción la API va detrás de Cloudflare, que sirve brotli (verificado con
   `Content-Encoding: br`); `php artisan serve` en local no comprime.
4. **Orden de la lista**: `sort(SORT_STRING | SORT_FLAG_CASE)`. Nombres con
   caracteres raros de TCGdex (`_____'s Pikachu`, `ナッシー[Exeggutor]`,
   `Mew ☆ δ`) van tal cual: son nombres reales de cartas y buscarlos lleva a
   resultados.
5. **La spec de 2026-09-12 no se ha tocado** (regla de CLAUDE.md), aunque
   esta la sustituye; sigue en `estado: propuesta`. Conviene marcarla como
   `descartada` a mano.
6. Al elegir una sugerencia en el catálogo se llama a `aplicar()` directamente
   en vez de disparar el evento `input`: el debounce de 300 ms no tiene sentido
   tras un clic, y el evento reabriría la lista con el nombre ya elegido.

## Archivos tocados

- `api/app/Services/TcgdexService.php` — `nombresDeCartas()`, `CACHE_TTL_NOMBRES`
- `api/app/Http/Controllers/CartaController.php` — `nombres()`
- `api/routes/api.php` — `/cartas/nombres` antes de `/cartas/{id}`
- `api/lang/es/mensajes.php`, `api/lang/en/mensajes.php` — `idioma_no_soportado`
- `api/tests/Feature/NombresDeCartasTest.php` — nuevo
- `frontend/js/autocompletado.js` — nuevo
- `frontend/js/header.js` — `activarAutocompletado()`, Enter condicionado
- `frontend/js/filtros-catalogo.js` — montaje
- `frontend/js/i18n/es.js`, `en.js` — una clave
- `frontend/css/estilos.css` — sección AUTOCOMPLETADO, `.buscador-contenedor { position: relative }`
- `tests-e2e/tests/caminos-criticos.spec.mjs` — un test

## Verificación

- `composer test` → **134 passed** (125 + 9).
- `tests-e2e`: **5 passed** (4 + el nuevo): índice pedido una vez al primer
  foco, lista con 1 letra, `Pikachu` con `pik`, sin repetidos, Escape cierra,
  ↓+Enter navega a `catalogo.html?q=<elegida>`, cero peticiones a
  `/cartas/buscar` mientras se escribe, roles ARIA presentes.
- Comprobado además con Playwright (no está en la suite): filtro del catálogo
  (`char` → 8 sugerencias, ↓+Enter aplica `?q=Charcadet`), drawer móvil a
  390 px (`mew` → 8), y captura del header de escritorio con la lista alineada
  bajo el input. Endpoint real: `Cache-Control`, `ETag`, segunda petición en
  0,35 s desde caché.
- [x] Criterios de backend: los 9 tests, uno por criterio, incluida la ruta
      que no se confunde con `/cartas/{id}`.
- [x] Criterios de frontend: e2e + sin `package.json` bajo `frontend/`
      (`git status` lo confirma) + textos por `t(...)`.

## Pendiente

- Marcar `docs/specs/2026-09-12-autocompletado-buscador.md` como `descartada`.
- Si algún día hay scheduler, refrescar el índice con un comando en vez de
  perezosamente en la primera petición tras las 24 h (esa petición paga la
  descarga de 2,3 MB: ~2-3 s una vez al día).
